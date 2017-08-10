<?php

namespace Driesboy\EggWars;

use pocketmine\entity\Villager;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\player\PlayerDeathEvent;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\player\PlayerMoveEvent;
use pocketmine\event\player\PlayerChatEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\event\block\SignChangeEvent;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\block\BlockPlaceEvent;
use pocketmine\block\Block;
use pocketmine\event\inventory\InventoryTransactionEvent;
use pocketmine\event\inventory\InventoryCloseEvent;
use pocketmine\inventory\ChestInventory;
use pocketmine\inventory\PlayerInventory;
use pocketmine\item\Item;
use pocketmine\tile\Sign;
use pocketmine\tile\Chest;
use pocketmine\event\Listener;
use pocketmine\level\Level;
use pocketmine\level\Position;
use pocketmine\Player;
use pocketmine\Server;
use pocketmine\math\Vector3;
use pocketmine\utils\Config;


class EventListener implements Listener{

  public $sd = array();
  public function __construct(){
  }

  public function OnQuit(PlayerQuitEvent $e){
    $main = EggWars::getInstance();
    $p = $e->getPlayer();
    if($main->IsInArena($p->getName())){
      $arena = $main->IsInArena($p->getName());
      $main->RemoveArenaPlayer($arena, $p->getName());
      $p->teleport(Server::getInstance()->getDefaultLevel()->getSafeSpawn());
      $message = $p->getNameTag()." §eleft the game!";
      $main->ArenaMessage($arena, $message);
    }
  }

  public function Chat(PlayerChatEvent $e){
    $p = $e->getPlayer();
    $m = $e->getMessage();
    $main = EggWars::getInstance();

    if($main->IsInArena($p->getName())){
      $color = "";
      $is = substr($m, 0, 1);
      $team = $main->PlayerTeamColor($p);
      $arena = $main->IsInArena($p->getName());
      $ac = new Config($main->getDataFolder()."Arenas/$arena.yml", Config::YAML);
      if($ac->get("Status") === "Lobby"){
        $players = $main->ArenaPlayer($arena);
        foreach($players as $Is){
          $to = $main->getServer()->getPlayer($Is);
          if($to instanceof Player){
            $to->sendMessage("§f".$p->getName()." §8» §7".$m);
          }
        }
      }
      if(!empty($main->Teams()[$team])){
        $color = $main->Teams()[$team];
      }
      if($is === "!"){
        $msil = substr($m, 1);
        $main->ArenaMessage($arena, "§8[§c!§8] ".$color.$p->getName()." §8» §7$msil");
      }else{
        $players = $main->ArenaPlayer($arena);
        foreach($players as $Is){
          $to = $main->getServer()->getPlayer($Is);
          if($to instanceof Player){
            $toTeam = $main->PlayerTeamColor($to);
            if($team === $toTeam){
              $message = "§8[".$color."team§8] ".$color.$p->getName()." §8» §7$m";
              $to->sendMessage($message);
            }
          }
        }
      }
      return;
    }
  }

  public function OnInteract(PlayerInteractEvent $e){
    $p = $e->getPlayer();
    $b = $e->getBlock();
    $t = $p->getLevel()->getTile($b);
    $main = EggWars::getInstance();
    if($t instanceof Sign){
      $yazilar = $t->getText();
      if($yazilar[0] === $main->tyazi){
        $arena = str_ireplace("§e", "", $yazilar[2]);
        $status = $main->ArenaStatus($arena);
        if($status === "Lobby"){
          if(!$main->IsInArena($p->getName())){
            $ac = new Config($main->getDataFolder()."Arenas/$arena.yml", Config::YAML);
            $players = count($main->ArenaPlayer($arena));
            $fullPlayer = $ac->get("Team") * $ac->get("PlayersPerTeam");
            if($players >= $fullPlayer){
              $p->sendPopup("§8» §cThis game is full! §8«");
              return;
            }
            $main->AddArenaPlayer($arena, $p->getName());
            $p->teleport(new Position($ac->getNested("Lobby.X"), $ac->getNested("Lobby.Y"), $ac->getNested("Lobby.Z"), $main->getServer()->getLevelByName($ac->getNested("Lobby.World"))));
            $main->TeamSellector($arena, $p);
            $main->ArenaMessage($arena, "§5".$p->getName()." §5joined the game. ". count($main->ArenaPlayer($arena)) . "/" .$ac->get("Team") * $ac->get("PlayersPerTeam"));
          }else{
            $p->sendPopup("§cYou're already in a game!");
          }
        }elseif ($status === "In-Game"){
          $p->sendPopup("§8» §dThe game is still going on!");
        }elseif ($status === "Done"){
          $p->sendPopup("§8» §eResetting the Arena ...");
        }
        $e->setCancelled();
      }
    }
  }

  public function UpgradeGenerator(PlayerInteractEvent $e){
    $p = $e->getPlayer();
    $b = $e->getBlock();
    $sign = $p->getLevel()->getTile($b);
    $main = EggWars::getInstance();
    if($sign instanceof Sign){
      $y = $sign->getText();
      if($y[0] === "§fIron" || $y[0] === "§6Gold" || $y[0] === "§bDiamond"){
        $tip = $y[0];
        $level = str_ireplace("§eLevel ", "", $y[1]);
        switch($level){
          case 0:
          switch ($tip){
            case "§6Gold":
            if($main->ItemId($p, Item::GOLD_INGOT) >= 5){
              $p->getInventory()->removeItem(Item::get(Item::GOLD_INGOT,0,5));
              $sign->setText($y[0], "§eLevel 1", "§b8 seconds", $y[3]);
              $p->sendMessage("§8» §aGold generator Activated!");
            }else{
              $p->sendMessage("§8» §65 Gold needed to upgrade!");
            }
            break;
            case "§bDiamond":
            if($main->ItemId($p, Item::DIAMOND) >= 5){
              $p->getInventory()->removeItem(Item::get(Item::DIAMOND,0,5));
              $sign->setText($y[0], "§eLevel 1", "§b10 seconds", $y[3]);
              $p->sendMessage("§8» §aDiamond generator Activated!");
            }else{
              $p->sendMessage("§8» §b5 Diamonds needed to upgrade!");
            }
            break;
          }
          break;
          case 1:
          switch ($tip){
            case "§fIron":
            if($main->ItemId($p, Item::IRON_INGOT) >= 10){
              $p->getInventory()->removeItem(Item::get(Item::IRON_INGOT,0,10));
              $sign->setText($y[0], "§eLevel 2", "§b2 seconds", $y[3]);
              $p->sendMessage("§8» §aUpgraded to level 2!");
            }else{
              $p->sendMessage("§8» §f10 Iron needed to upgrade!");
            }
            break;
            case "§6Gold":
            if($main->ItemId($p, Item::GOLD_INGOT) >= 10){
              $p->getInventory()->removeItem(Item::get(Item::GOLD_INGOT,0,10));
              $sign->setText($y[0], "§eLevel 2", "§b6 seconds", $y[3]);
              $p->sendMessage("§8» §aUpgraded to level 2!");
            }else{
              $p->sendMessage("§8» §610 Gold needed to upgrade!");
            }
            break;
            case "§bDiamond":
            if($main->ItemId($p, Item::DIAMOND) >= 10){
              $p->getInventory()->removeItem(Item::get(Item::DIAMOND,0,10));
              $sign->setText($y[0], "§eLevel 2", "§b8 seconds", $y[3]);
              $p->sendMessage("§8» §aUpgraded to level 2!");
            }else{
              $p->sendMessage("§8» §b10 Diamonds needed to upgrade!");
            }
            break;
          }
          break;
          case 2:
          switch ($tip){
            case "§fIron":
            if($main->ItemId($p, Item::GOLD_INGOT) >= 10){
              $p->getInventory()->removeItem(Item::get(Item::GOLD_INGOT,0,10));
              $sign->setText($y[0], "§eLevel 3", "§b1 seconds", "§c§lMAXIMUM");
              $p->sendMessage("§8» §aMaximum Level raised!");
            }else{
              $p->sendMessage("§8» §610 Gold needed to upgrade!");
            }
            break;
            case "§6Gold":
            if($main->ItemId($p, Item::DIAMOND) >= 10){
              $p->getInventory()->removeItem(Item::get(Item::DIAMOND,0,10));
              $sign->setText($y[0], "§eLevel 3", "§b4 seconds", "§c§lMAXIMUM");
              $p->sendMessage("§8» §aMaximum Level raised!");
            }else{
              $p->sendMessage("§8» §b10 Diamonds needed to upgrade!");
            }
            break;
            case "§bDiamond":
            if($main->ItemId($p, Item::DIAMOND) >= 20){
              $p->getInventory()->removeItem(Item::get(Item::DIAMOND,0,20));
              $sign->setText($y[0], "§eLevel 3", "§b6 seconds", "§c§lMAXIMUM");
              $p->sendMessage("§8» §aMaximum Level raised!");
            }else{
              $p->sendMessage("§8» §b20 Diamonds needed to upgrade!");
            }
            break;
          }
          break;
          default:
          $p->sendMessage("§8» §cThis generator is already on the Maximum level!");
          break;
        }
      }
    }
  }

  public function DestroyEgg(PlayerInteractEvent $e){
    $p = $e->getPlayer();
    $b = $e->getBlock();
    $main = EggWars::getInstance();
    if($main->IsInArena($p->getName())){
      if($b->getId() === 122){
        $yun = $b->getLevel()->getBlock(new Vector3($b->x, $b->y - 1, $b->z));
        if($yun->getId() === 35){
          $color = $yun->getDamage();
          $team = array_search($color, $main->TeamSearcher());
          $pht = $main->PlayerTeamColor($p);
          if($pht === $team){
            $p->sendPopup("§8»§c You can not break your own egg!");
            $e->setCancelled();
          }else{
            $b->getLevel()->setBlock(new Vector3($b->x, $b->y, $b->z), Block::get(0));
            $main->CreateLightning($b->x, $b->y, $b->z, $p->getLevel());
            $arena = $main->IsInArena($p->getName());
            $main->ky[$arena][] = $team;
            $main->ArenaMessage($main->IsInArena($p->getName()), "§eTeam " .$main->Teams()[$team]."$team's".$main->Teams()[$pht]." §eegg has been destroyed by " .$p->getNameTag());
          }
        }
      }
    }
  }

  public function CreateSign(SignChangeEvent $e){
    $p = $e->getPlayer();
    $main = EggWars::getInstance();
    if($p->isOp()){
      if($e->getLine(0) === "eggwars"){
        if(!empty($e->getLine(1))){
          if($main->ArenaControl($e->getLine(1))){
            if($main->ArenaReady($e->getLine(1))){
              $arena = $e->getLine(1);
              $e->setLine(0, $main->tyazi);
              $e->setLine(1, "§f0/0");
              $e->setLine(2, "§e$arena");
              $e->setLine(3, "§l§bTap to Join");
              for($i=0; $i<=3; $i++){
                $p->sendMessage("§8» §a$i".$e->getLine($i));
              }
            }else{
              $e->setLine(0, "§cERROR");
              $e->setLine(1, "§7".$e->getLine(1));
              $e->setLine(2, "§7Arena");
              $e->setLine(3, "§7not exactly!");
            }
          }else{
            $e->setLine(0, "§cERROR");
            $e->setLine(1, "§7".$e->getLine(1));
            $e->setLine(2, "§7Arena");
            $e->setLine(3, "§7Not found");
          }
        }else{
          $e->setLine(0, "§cERROR");
          $e->setLine(1, "§7Arena");
          $e->setLine(2, "§7Section");
          $e->setLine(3, "§7null!");
        }
      }elseif ($e->getLine(0) === "generator"){
        if(!empty($e->getLine(1))){
          switch ($e->getLine(1)){
            case "Iron":
            $e->setLine(0, "§fIron");
            $e->setLine(1, "§eLevel 1");
            $e->setLine(2, "§b4 seconds");
            $e->setLine(3, "§a§lUpgrade");
            break;
            case "Gold":
            if($e->getLine(2) != "Broken") {
              $e->setLine(0, "§6Gold");
              $e->setLine(1, "§eLevel 1");
              $e->setLine(2, "§b8 seconds");
              $e->setLine(3, "§a§lUpgrade");
            }else{
              $e->setLine(0, "§6Gold");
              $e->setLine(1, "§eLevel 0");
              $e->setLine(2, "§bBroken");
              $e->setLine(3, "§a§l-------");
            }
            break;
            case "Diamond":
            if($e->getLine(2) != "Broken") {
              $e->setLine(0, "§bDiamond");
              $e->setLine(1, "§eLevel 1");
              $e->setLine(2, "§b10 seconds");
              $e->setLine(3, "§a§lUpgrade");
            }else{
              $e->setLine(0, "§bDiamond");
              $e->setLine(1, "§eLevel 0");
              $e->setLine(2, "§bBroken");
              $e->setLine(3, "§a§l-------");
            }
            break;
          }
        }else{
          $e->setLine(0, "§cERROR");
          $e->setLine(1, "§7generator");
          $e->setLine(2, "§7Type");
          $e->setLine(3, "§7unspecified!");
        }
      }
    }
  }

  public function onDeath(PlayerDeathEvent $e){
    $p = $e->getPlayer();
    $main = EggWars::getInstance();
    if($main->IsInArena($p->getName())){
      $e->setDeathMessage("");
      $sondarbe = $p->getLastDamageCause();
      if($sondarbe instanceof EntityDamageByEntityEvent){
        $e->setDrops(array());
        $plduren = $sondarbe->getDamager();
        if($plduren instanceof Player){
          $main->ArenaMessage($main->IsInArena($p->getName()), $p->getNameTag()." §ewas killed by ".$plduren->getNameTag());
        }
      }else{
        $e->setDrops(array());
        if(!empty($this->sd[$p->getName()])){
          $plduren = $main->getServer()->getPlayer($this->sd[$p->getName()]);
          if($plduren instanceof Player){
            $main->ArenaMessage($main->IsInArena($p->getName()), $p->getNameTag()." §ewas killed by ".$plduren->getNameTag());
          }
        }else{
          $main->ArenaMessage($main->IsInArena($p->getName()), $p->getNameTag()." §edied!");
        }
      }
    }
  }

  public function Damage(EntityDamageEvent $e){
    $p = $e->getEntity();
    $main = EggWars::getInstance();
    if($e instanceof EntityDamageByEntityEvent){
      $d = $e->getDamager();
      if($p instanceof Villager && $d instanceof Player){
        if($p->getNameTag() === "§6EggWars Shop"){
          $e->setCancelled();
          $main->m[$d->getName()] = "ok";
          $main->EmptyShop($d);
        }
      }
      if($p instanceof Player && $d instanceof Player){
        if($main->IsInArena($p->getName())){
          $arena = $main->IsInArena($p->getName());
          $ac = new Config($main->getDataFolder()."Arenas/$arena.yml", Config::YAML);
          $team = $main->PlayerTeamColor($p);
          if($ac->get("Status") === "Lobby"){
            $e->setCancelled();
          }else{
            $td = substr($d->getNameTag(), 0, 3);
            $to = substr($p->getNameTag(), 0, 3);
            if($td === $to){
              $e->setCancelled();
            }else{
              $this->sd[$p->getName()] = $d->getName();
            }
          }
          if($e->getDamage() >= $e->getEntity()->getHealth()){
            $e->setCancelled();
            $p->setHealth(20);
            $p->setFood(20);
            if($main->EggSkin($arena, $team)){
              $main->RemoveArenaPlayer($arena, $p->getName());
            }else{
              $p->teleport(new Position($ac->getNested("$team.X"), $ac->getNested("$team.Y"), $ac->getNested("$team.Z"), $main->getServer()->getLevelByName($ac->get("World"))));
              $main->ArenaMessage($arena, $p->getNameTag()." §ewas killed by ".$d->getNameTag());
            }
            $p->getInventory()->clearAll();
            $p->getInventory()->sendContents($p);
          }
        }
      }
    }else{
      if($p instanceof Player){
        if($main->IsInArena($p->getName())){
          $arena = $main->IsInArena($p->getName());
          $ac = new Config($main->getDataFolder()."Arenas/$arena.yml", Config::YAML);
          if($ac->get("Status") === "Lobby"){
            $e->setCancelled();
          }
          $team = $main->PlayerTeamColor($p);
          $message = null;
          if(!empty($this->sd[$p->getName()])){
            $sd = $main->getServer()->getPlayer($this->sd[$p->getName()]);
            if($sd instanceof Player){
              unset($this->sd[$p->getName()]);
              $message = $p->getNameTag()." §ewas killed by ".$sd->getNameTag();
            }else{
              $message = $p->getNameTag()." §edied!";
            }
          }else{
            $message = $p->getNameTag()." §edied!";
          }
          if($e->getDamage() >= $e->getEntity()->getHealth()){
            $e->setCancelled();
            $p->setHealth(20);
            $p->setFood(20);
            if($main->EggSkin($arena, $team)){
              $pname = $p->getName();
              $main->RemoveArenaPlayer($arena, $p->getName());
              $main->ArenaMessage($arena, $message);
              $main->ArenaMessage($arena, "§c$pname has been eliminated from the game.");

            }else{
              $p->teleport(new Position($ac->getNested("$team.X"), $ac->getNested("$team.Y"), $ac->getNested("$team.Z"), $main->getServer()->getLevelByName($ac->get("World"))));
              $main->ArenaMessage($arena, $message);
            }
            $p->getInventory()->clearAll();
            $p->getInventory()->sendContents($p);
          }
        }
      }
    }
  }

  public function envKapat(InventoryCloseEvent $e){
    $p = $e->getPlayer();
    $env = $e->getInventory();
    $main = EggWars::getInstance();
    if($env instanceof ChestInventory){
      if(!empty($main->m[$p->getName()])){
        $p->getLevel()->setBlock(new Vector3($p->getFloorX(), $p->getFloorY() - 4, $p->getFloorZ()), Block::get(Block::AIR));
        unset($main->m[$p->getName()]);
      }
    }
  }

  public function StoreEvent(InventoryTransactionEvent $e){
    $envanter = $e->getTransaction()->getInventories();
    $trans = $e->getTransaction()->getTransactions();
    $main = EggWars::getInstance();
    $p = null;
    $sb = null;
    $transfer = null;
    foreach($envanter as $env){
      $Held = $env->getHolder();
      if($Held instanceof Chest){
        $sb = $Held->getBlock();
      }
      if($Held instanceof Player){
        $p = $Held;
      }
    }

    foreach($trans as $t){
      if($t->getInventory() instanceof PlayerInventory){
        $transfer = $t;
      }
    }

    if($p != null and $sb != null and $transfer != null){

      $shopc = new Config($main->getDataFolder()."shop.yml", Config::YAML);
      $shop = $shopc->get("shop");
      $sandik = $p->getLevel()->getTile($sb);
      if($sandik instanceof Chest){
        $item = $transfer->getTargetItem();
        $si = $sandik->getInventory();

        if(empty($main->m[$p->getName()])){
          $itemler = 0;
          for($i=0; $i<count($shop); $i += 2){
            $slot = $i / 2;
            if($item->getId() === $shop[$i]){
              $itemler++;
            }
          }
          if($itemler === count($shop)){
            $main->m[$p->getName()] = 1;
          }
        }else{
          $e->setCancelled();
          if($item->getId() === 35 && $item->getDamage() === 14){
            $e->setCancelled();
            $shopc->reload();
            $shop = $shopc->get("shop");
            $sandik->getInventory()->clearAll();
            for($i=0; $i<count($shop); $i += 2){
              $slot = $i / 2;
              $sandik->getInventory()->setItem($slot, Item::get($shop[$i], 0, 1));
            }
          }
          $transSlot = 0;
          for($i=0; $i<$si->getSize(); $i++){
            if($si->getItem($i)->getId() === $item->getId()){
              $transSlot = $i;
              break;
            }
          }
          $is = $si->getItem(1)->getId();
          if($transSlot % 2 != 0 && ($is === 264 or $is === 265 or $is === 266)){
            $e->setCancelled();
          }
          if($item->getId() === 264 or $item->getId() === 265 or $item->getId() === 266){
            $e->setCancelled();
          }
          if($transSlot % 2 === 0 && ($is === 264 or $is === 265 or $is === 266)){
            $ucret = $si->getItem($transSlot + 1)->getCount();
            $para = $main->ItemId($p, $si->getItem($transSlot + 1)->getId());
            if($para >= $ucret){
              $p->getInventory()->removeItem(Item::get($si->getItem($transSlot + 1)->getId(), 0, $ucret));
              $aitemd = $si->getItem($transSlot);
              $aitem = Item::get($aitemd->getId(), $aitemd->getDamage(), $aitemd->getCount());
              $p->getInventory()->addItem($aitem);
            }
            $e->setCancelled();
          }
          if($is != 264 or $is != 265 or $is != 266){
            $e->setCancelled();
            $shopc->reload();
            $shop = $shopc->get("shop");
            for($i=0; $i<count($shop); $i+=2){
              if($item->getId() === $shop[$i]){
                $sandik->getInventory()->clearAll();
                $gyer = $shop[$i+1];
                $slot = 0;
                for($e=0; $e<count($gyer); $e++){
                  $sandik->getInventory()->setItem($slot, Item::get($gyer[$e][0], 0, $gyer[$e][1]));
                  $slot++;
                  $sandik->getInventory()->setItem($slot, Item::get($gyer[$e][2], 0, $gyer[$e][3]));
                  $slot++;
                }
                break;
              }
            }
            $sandik->getInventory()->setItem($sandik->getInventory()->getSize() - 1, Item::get(Item::WOOL, 14, 1));
          }
        }
      }
    }

  }

  public function BlockBreakEvent(BlockBreakEvent $e){
    $p = $e->getPlayer();
    $b = $e->getBlock();
    $main = EggWars::getInstance();
    if($main->IsInArena($p->getName())){
      $cfg = new Config($main->getDataFolder()."config.yml", Config::YAML);
      $ad = $main->ArenaStatus($main->IsInArena($p->getName()));
      if($ad === "Lobby"){
        $e->setCancelled(true);
        return;
      }
      $bloklar = $cfg->get("BuildBlocks");
      foreach($bloklar as $blok){
        if($b->getId() != $blok){
          $e->setCancelled();
        }else{
          $e->setCancelled(false);
          break;
        }
      }
    }
  }

  public function BlockPlaceEvent(BlockPlaceEvent $e){
    $p = $e->getPlayer();
    $b = $e->getBlock();
    $main = EggWars::getInstance();
    $cfg = new Config($main->getDataFolder()."config.yml", Config::YAML);
    if($main->IsInArena($p->getName())){
      $ad = $main->ArenaStatus($main->IsInArena($p->getName()));
      if($ad === "Lobby"){
        if($b->getId() === 35){
          $arena = $main->IsInArena($p->getName());
          $tyun = array_search($b->getDamage() ,$main->TeamSearcher());
          $marena = $main->AvailableTeams($arena);
          if(in_array($tyun, $marena)){
            $color = $main->Teams()[$tyun];
            $p->setNameTag($color.$p->getName());
            $p->sendPopup("§8» Team $color"."$tyun Selected!");
          }else{
            $p->sendPopup("§8» §cTeams must be equal!");
          }
          $e->setCancelled();
        }
        $e->setCancelled();
        return;
      }

      $bloklar = $cfg->get("BuildBlocks");
      foreach($bloklar as $blok){
        if($b->getId() != $blok){
          $e->setCancelled();
        }else{
          $e->setCancelled(false);
          break;
        }
      }
    }
  }

}
