<?php

namespace awzaw\megaban;

use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerPreLoginEvent;
use pocketmine\Player;
use pocketmine\plugin\PluginBase;

class Main extends PluginBase implements Listener {

    private $bans = [];

    public function onEnable() {

        $this->getServer()->getPluginManager()->registerEvents($this, $this);
        $this->saveDefaultConfig();

        @mkdir($this->getDataFolder());

        if (file_exists($this->getDataFolder() . "megabans.txt")) {
            $file = file($this->getDataFolder() . "megabans.txt", FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($file as $line) {
                $array = explode("|", trim($line));
                if (!isset($array[2]))
                    $array[2] = "";
                if (!isset($array[3]))
                    $array[3] = "";
                $this->bans[$array[0]] = array($array[1], $array[2], $array[3]); // SKIN KEY => NAME, CID, IP
            }
        }
    }

    public function onDisable() {
        $this->saveData();
    }

    private function saveData() {
        $string = "";
        foreach ($this->bans as $client => $data) {
            $string .= $client . "|" . $data["name"] . "|" . $data["CID"] . "|" . $data["IP"] . "\n";
        }
        file_put_contents($this->getDataFolder() . "megabans.txt", $string);
    }

    public function banClient(Player $player) {

        //IP Ban
        $this->getServer()->getIPBans()->addBan($player->getAddress(), $this->getConfig()->get("message"), null, $player->getName());
        $this->getServer()->getNetwork()->blockAddress($player->getAddress(), -1);
        $player->setBanned(true);

        //Record Skin Hash, CID and IP Address: TODO add config to choose which bans are in force
        $this->bans[hash("md5", $player->getSkin()->getSkinData())] = ["name" => strtolower($player->getName()), "CID" => $player->getUniqueId(), "IP" => $player->getAddress()];

        $string = $this->getConfig()->get("banmessage");
        $player->kick($string);
        $this->saveData();
    }

    public function pardonClient(string $name) {
        if (($key = $this->in_array_r(strtolower($name), $this->bans)) !== false) {
            $ipaddress = $this->bans[$key]["IP"];
            unset($this->bans[$key]);
            $this->saveData();
            $this->getServer()->getNameBans()->remove($name);
            $this->getServer()->getIPBans()->remove($ipaddress);
            return true;
        }
        return false;
    }

    public function isBanned($banned) {

        if ($banned instanceof Player) {
            $bannedskin = hash("md5", $banned->getSkin()->getSkinData());
        } else {
            return false;
        }
        return (isset($this->bans[$bannedskin]) || $this->in_array_r($banned->getUniqueId(), $this->bans));
    }

    public function onCommand(CommandSender $sender, Command $command, string $label, array $args) : bool{
        switch (strtolower($command->getName())) {

            case "megaban":
                if (!isset($args[0])) {
                    return false;
                }

                if (strtolower($args[0]) === "clear") {
                    $this->bans = [];
                    $this->saveData();
                    $sender->sendMessage("All MegaBan data cleared. Players may still be Banned or IP Banned");
                    return true;
                }

                $p = array_shift($args);
                $player = $this->getServer()->getPlayer($p);
                if ($player !== null and $player->isOnline()) {
                    $this->banClient($player);
                    $sender->sendMessage("MegaBanned " . $p);
                } else {
                    $sender->sendMessage("Player " . $p . " is not online");
                }
                return true;

            case "megaunban":
            case "megapardon":
                if (!isset($args[0])) {
                    return false;
                }
                if ($this->pardonClient($args[0])) {
                    $sender->sendMessage("On next reboot MegaBan will be removed for " . $args[0]);
                } else {
                    $sender->sendMessage($args[0] . " was not MegaBanned");
                }
                return true;
        }
        return true;
    }

    public function onPreLogin(PlayerPreLoginEvent $event) {
        if ($event->isCancelled()) return;
        if ($this->isBanned($event->getPlayer())) {
            $event->setKickMessage($this->getConfig()->get("banmessage"));
            $event->setCancelled(true);
        }
    }

    public function in_array_r($needle, $haystack, $strict = false) {
        foreach ($haystack as $key => $item) {
            if (($strict ? $item === $needle : $item == $needle) || (is_array($item) && $this->in_array_r($needle, $item, $strict))) {
                return $key;
            }
        }

        return false;
    }

}
