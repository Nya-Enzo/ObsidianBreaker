<?php

namespace Enzo\ObsidianBreaker\block;

use Enzo\ObsidianBreaker\ObsidianBreaker;
use pocketmine\block\BlockBreakInfo;
use pocketmine\block\BlockFactory;
use pocketmine\block\BlockIdentifier;
use pocketmine\block\Opaque;
use pocketmine\block\VanillaBlocks;
use pocketmine\item\Durable;
use pocketmine\item\Item;
use pocketmine\item\ItemIds;
use pocketmine\item\StringToItemParser;
use pocketmine\item\VanillaItems;
use pocketmine\math\Facing;
use pocketmine\math\Vector3;
use pocketmine\player\Player;
use pocketmine\scheduler\ClosureTask;
use pocketmine\Server;
use pocketmine\world\particle\BlockBreakParticle;
use pocketmine\world\particle\BlockForceFieldParticle;
use pocketmine\world\particle\BlockPunchParticle;
use pocketmine\world\sound\BlockBreakSound;

class ExplodableObsidian extends Opaque //Obsidian
{
    public function __construct(BlockIdentifier $idInfo, string $name, BlockBreakInfo $breakInfo)
    {
        parent::__construct($idInfo, $name, $breakInfo);
    }

    public function getCurrentExplosions(): int
    {
        return $this->getMeta();
    }

    protected function writeStateToMeta(): int
    {
        return 0;
    }

    public function getStateBitmask(): int
    {
        return 0;
    }

    public function updateExplosion(): void
    {
        $max = ObsidianBreaker::getInstance()->getConfig()->get("obsidian-durability", 4);
        $world = $this->getPosition()->getWorld();
        $pos = $this->getPosition();

        $newMeta = $this->getMeta() + 1;
        if($max !== -1 && $newMeta >= $max)
        {
            $world->setBlock($pos, VanillaBlocks::AIR());
            $world->addParticle($pos->add(0.5, 0, 0.5), new BlockBreakParticle($this));
            $world->addSound($pos, new BlockBreakSound($this));
            if(ObsidianBreaker::getInstance()->getConfig()->get("obsidian-drop", true) == true)
            {
                ObsidianBreaker::getInstance()->getScheduler()->scheduleDelayedTask(new ClosureTask(function ()
                use($world, $pos): void
                {
                    $world->dropItem($pos, $this->asItem(), null, 15);
                }), 30);
            }

            return;
        }

        $world->setBlock($pos, BlockFactory::getInstance()->get($this->getId(), $newMeta, 1));
        $world->addParticle($pos->add(0.5, 1, 0.5), new BlockPunchParticle($this, 0));
    }

    public function onInteract(Item $item, int $face, Vector3 $clickVector, ?Player $player = null): bool
    {
        $parent = parent::onInteract($item, $face, $clickVector, $player);
        if($player)
        {
            $checkerWand = ObsidianBreaker::getInstance()->getConfig()->get("durability-wand", '');
            if($checkerWand !== '')
            {
                $checkerWandItem = StringToItemParser::getInstance()->parse($checkerWand);
                if($checkerWandItem && $item->equals($checkerWandItem, true, false))
                {
                    $max = ObsidianBreaker::getInstance()->getConfig()->get("obsidian-durability", 4);
                    $message = str_replace("{durability}", $max - $this->getMeta(),
                        ObsidianBreaker::getInstance()->getConfig()->get("durability-message", ""));
                    $player->sendMessage($message);
                    if($checkerWandItem instanceof Durable)
                        $checkerWandItem->applyDamage(1);

                    return true;
                }
            }

            $breakerWand = ObsidianBreaker::getInstance()->getConfig()->get("magical-wand", '');
            if($breakerWand !== '')
            {
                $breakerWandItem = StringToItemParser::getInstance()->parse($breakerWand);
                if($breakerWandItem && $item->equals($breakerWandItem, true, false))
                {
                    $drops = ObsidianBreaker::getInstance()->getConfig()->get("wand-drop", false) == true;

                    $this->getPosition()->getWorld()->setBlock($this->getPosition(), VanillaBlocks::AIR());
                    if($drops)
                    {
                        $this->getPosition()->getWorld()->dropItem($this->getPosition(), $this->asItem(), null, 15);
                    }

                    if($breakerWandItem instanceof Durable)
                        $breakerWandItem->applyDamage(1);

                    return true;
                }
            }
        }

        return $parent;
    }
}