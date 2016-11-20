<?php

namespace awzaw\megaban;

use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerPreLoginEvent;
use pocketmine\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\Server;

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
                $this->bans[$array[0]] = $array[1];
            }
        }
    }

    public function onDisable() {
        $this->saveData();
    }

    private function saveData() {
        $string = "";
        foreach ($this->bans as $client => $name) {
            $string .= $client . "|" . $name . "\n";
        }
        file_put_contents($this->getDataFolder() . "megabans.txt", $string);
    }

    public function banClient(Player $player) {

        if (method_exists($this->getServer(), "getCIDBans")) {
            $this->getServer()->getCIDBans()->addBan($player->getClientId(), $this->getConfig()->get("message"), null, $player->getName());
            $this->getServer()->getIPBans()->addBan($player->getAddress(), $this->getConfig()->get("message"), null, $player->getName());
            $this->getServer()->getNetwork()->blockAddress($player->getAddress(), -1);
            $player->setBanned(true);
        } else {

            $this->getServer()->getIPBans()->addBan($player->getAddress(), $this->getConfig()->get("message"), null, $player->getName());
            $this->getServer()->getNetwork()->blockAddress($player->getAddress(), -1);
            $player->setBanned(true);
        }

        $this->bans[hash("md5", $player->getSkinData())] = strtolower($player->getName());
        $string = $this->getConfig()->get("banmessage");
        $player->kick($string);
        $this->saveData();
    }

    public function pardonClient(string $name) {
        if (($key = array_search(strtolower($name), $this->bans)) !== false) {
            unset($this->bans[$key]);
            $this->saveData();
            return true;
        }
        return false;
    }

    public function isBanned($banned) {

        if ($banned instanceof Player) {
            $banned = hash("md5", $banned->getSkinData());
        }
        return isset($this->bans[$banned]);
    }

    public function onCommand(CommandSender $sender, Command $command, $label, array $args) {
        switch (strtolower($command->getName())) {

            case "megaban":
                if (!isset($args[0])) {
                    return false;
                }

                if (strtolower($args[0]) === "clear") {
                    $this->bans = [];
                    $this->saveData();
                    $sender->sendMessage("MegaBan list cleared, you still need to pardon, pardon IP etc");
                    return true;
                }

                $p = array_shift($args);
                $player = $this->getServer()->getPlayer($p);
                if ($player !== null and $player->isOnline()) {
                    $this->banClient($player, isset($args[0]) ? implode(" ", $args) : "");
                    $sender->sendMessage("MegaBanned " . $p);
                } else {
                    $sender->sendMessage("Player " . $p . " is not online");
                }
                return true;
                break;

            case "megaunban":
                if (!isset($args[0])) {
                    return false;
                }
                if ($this->pardonClient($args[0])) {
                    $sender->sendMessage("MegaBan removed for " . $args[0]);
                } else {
                    $sender->sendMessage($args[0] . " was not MegaBanned");
                }
                return true;
                break;
        }
        return true;
    }

    public function onPreLogin(PlayerPreLoginEvent $event) {
        if ($this->isBanned($event->getPlayer())) {
            $event->setKickMessage($this->getConfig()->get("banmessage"));
            $event->setCancelled();
        }
    }

}
