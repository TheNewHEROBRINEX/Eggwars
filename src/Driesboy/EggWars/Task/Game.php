<?php

namespace Driesboy\EggWars\Task;

use pocketmine\scheduler\PluginTask;
use pocketmine\Server;
use pocketmine\tile\Sign;
use pocketmine\utils\Config;
use pocketmine\Player;
use pocketmine\level\Position;
use pocketmine\item\Item;
use pocketmine\math\Vector3;
use pocketmine\math\AxisAlignedBB;

class Game extends PluginTask{

  private $p;
  public function __construct($p){
    $this->p = $p;
    parent::__construct($p);
  }

  public function onRun(int $tick){
    $main = $this->p;
    foreach($main->Arenas() as $arena){
      if($main->ArenaReady($arena)){
        $ac = new Config($main->getDataFolder()."Arenas/$arena.yml", Config::YAML);
        $status = $ac->get("Status");
        if($status === "Lobby"){
          $time = (int) $ac->get("StartTime");
          if($time > 0 || $time <= 0){
            if(count($main->ArenaPlayer($arena)) >= $ac->get("Team")){
              $time--;
              $ac->set("StartTime", $time);
              $ac->save();
              switch ($time){
                case 120:
                $main->ArenaMessage($arena, "§9EggWars starting in 2 minutes");
                break;
                case 90:
                $main->ArenaMessage($arena, "§9EggWars starting in 1 minute and 30 seconds");
                break;
                case 60:
                $main->ArenaMessage($arena, "§9EggWars starting in 1 minute");
                break;
                case 30:
                case 15:
                case 5:
                case 4:
                case 3:
                case 2:
                case 1:
                $main->ArenaMessage($arena, "§9EggWars starting in $time seconds");
                break;
                default:
                if($time <= 0) {
                  foreach ($main->ArenaPlayer($arena) as $Is) {
                    $p = $main->getServer()->getPlayer($Is);
                    if ($p instanceof Player) {
                      if (!$main->PlayerTeamColor($p)) {
                        $team = $main->AvailableRastTeam($arena);
                        $p->setNameTag($team . $p->getName());
                      }
                      $team = $main->PlayerTeamColor($p);
                      $p->teleport(new Position($ac->getNested($team . ".X"), $ac->getNested($team . ".Y"), $ac->getNested($team . ".Z"), $main->getServer()->getLevelByName($ac->get("World"))));
                      $p->getInventory()->clearAll();
                      $p->setFood(20);
                      $p->sendMessage("§1Go!");
                    }
                  }
                  $ac->set("Status", "In-Game");
                  $ac->save();
                }
                break;
              }
              $all = $main->ArenaPlayer($arena);
              foreach($all as $p){
                $p = $main->getServer()->getPlayer($p);
                if($p instanceof Player){
                  $p->setXpLevel($time);
                }
              }
            }
          }
        }elseif($status === "In-Game"){
          $level = Server::getInstance()->getLevelByName($ac->get("World"));
          $tile = $level->getTiles();
          foreach ($tile as $sign){
            if($sign instanceof Sign){
              $y = $sign->getText();
              if($y[0] === "§fIron" || $y[0] === "§6Gold" || $y[0] === "§bDiamond"){
                $evet = false;
                foreach($level->getNearbyEntities(new AxisAlignedBB($sign->x - 10, $sign->y - 10, $sign->z - 10, $sign->x + 10, $sign->y + 10, $sign->z + 10)) as $ent){
                  if($ent instanceof Player){
                    $evet = true;
                  }
                }
                if($evet === true){
                  $im = explode(" ", $y[2]);
                  $second = str_ireplace("§b", "", $im[0]);
                  $tur = $y[0];
                  if($second != "Broken"){
                    $item = $this->turDonusItem($tur);
                    if(time() % $second === 0){
                      $level->dropItem(new Vector3($sign->x, $sign->y, $sign->z), $item);
                    }
                  }
                }
              }
            }
          }
          foreach($main->ArenaPlayer($arena) as $Is){
            $p = Server::getInstance()->getPlayer($Is);
            $i = null;
            foreach($main->Status($arena) as $status){
              $i.=$status;
            }
            $p->sendPopup($i);
          }
          if($main->OneTeamRemained($arena)){
            $ac->set("Status", "Done");
            $ac->save();
            $main->ArenaMessage($arena, "§aCongratulations, you win!");
            foreach ($main->ArenaPlayer($arena) as $Is) {
              $p = Server::getInstance()->getPlayer($Is);
              if(!($p instanceof Player)){
                return true;
              }
              $team = $main->PlayerTeamColor($p);
            }
            Server::getInstance()->broadcastMessage("$team §9won the game on §b$arena!");
          }
        }elseif($status === "Done"){
          $bitis = (int) $ac->get("EndTime");
          if($bitis > 0 || $bitis <= 0){
            $bitis--;
            $ac->set("EndTime", $bitis);
            $ac->save();
            foreach($main->ArenaPlayer($arena) as $players){
              $p = Server::getInstance()->getPlayer($players);
              if($bitis <= 1){
                $main->RemoveArenaPlayer($arena, $p->getName());
              }
            }
            if($bitis <= 0){
              $main->ArenaRefresh($arena);
              return;
            }
          }
        }else{
          $ac->set("Status", "Done");
          $ac->save();
        }
      }
    }
  }

  public function turDonusItem($tur){
    $item = null;
    switch($tur){
      case "§6Gold":
      $item = Item::get(266);
      break;
      case "§bDiamond":
      $item = Item::get(264);
      break;
      default:
      $item = Item::get(265);
      break;
    }
    return $item;
  }
}
