<?php

namespace Enzo\ObsidianBreaker;

use Enzo\ObsidianBreaker\block\ExplodableObsidian;
use pocketmine\block\BlockBreakInfo;
use pocketmine\block\BlockFactory;
use pocketmine\block\BlockIdentifier;
use pocketmine\block\BlockLegacyIds;
use pocketmine\block\BlockToolType;
use pocketmine\block\VanillaBlocks;
use pocketmine\entity\object\PrimedTNT;
use pocketmine\entity\projectile\Egg;
use pocketmine\entity\projectile\Snowball;
use pocketmine\event\entity\EntityExplodeEvent;
use pocketmine\event\Listener;
use pocketmine\item\ItemIds;
use pocketmine\item\ToolTier;
use pocketmine\network\mcpe\convert\RuntimeBlockMapping;
use pocketmine\plugin\PluginBase;
use pocketmine\scheduler\AsyncTask;
use pocketmine\Server;
use const pocketmine\RESOURCE_PATH;

class ObsidianBreaker extends PluginBase implements Listener
{
    private static ?ObsidianBreaker $instance = null;

    public static function getInstance(): self { return self::$instance; }

    public static function runtimeManagement()
    {
        if(!self::$instance)
            return;

        //For runtime ids
        /** @see RuntimeBlockMapping::registerMapping() */
        $registerMapping = new \ReflectionMethod(RuntimeBlockMapping::class, 'registerMapping');
        $registerMapping->setAccessible(true);

        $runtimeId = RuntimeBlockMapping::getInstance()->toRuntimeId(VanillaBlocks::OBSIDIAN()->getFullId());
        $legacyId = BlockLegacyIds::OBSIDIAN;

        for($meta = 0; $meta < ObsidianBreaker::getInstance()->getConfig()->get("obsidian-durability", 4); $meta++)
        {
            $registerMapping->invoke(RuntimeBlockMapping::getInstance(), $runtimeId, $legacyId, $meta);
        }
    }

    public function onLoad(): void
    {
        self::$instance = $this;
        self::runtimeManagement();
    }

    public function onEnable(): void
    {
        Server::getInstance()->getAsyncPool()->addWorkerStartHook(function(int $worker): void
        {
            Server::getInstance()->getAsyncPool()->submitTaskToWorker(new class() extends AsyncTask
            {
                public function onRun(): void
                {
                    ObsidianBreaker::runtimeManagement();
                }
            }, $worker);
        });

        $this->saveDefaultConfig();

        $breakInfo = new BlockBreakInfo(35.0, BlockToolType::PICKAXE, ToolTier::DIAMOND()->getHarvestLevel(), 10.0);
        for($i = 0; $i < $this->getConfig()->get("obsidian-durability", 4); $i++)
        {
            $name = $i === 0 ? "Obsidian" : "Obsidian, Exploded x$i";
            BlockFactory::getInstance()->register(new ExplodableObsidian(new BlockIdentifier(BlockLegacyIds::OBSIDIAN, $i, ItemIds::OBSIDIAN),
                $name, $breakInfo), true);
        }

        $this->getServer()->getPluginManager()->registerEvents($this, $this);
    }

    public function onBlockExplode(EntityExplodeEvent $event)
    {
        $list = $event->getBlockList();
        $newList = [];
        $entity = $event->getEntity();

        if($entity instanceof PrimedTNT)
        {
            foreach($list as $block)
            {
                if($block instanceof ExplodableObsidian)
                {
                    $block->updateExplosion();
                    continue;
                }

                $newList[] = $block;
            }
        }

        $event->setBlockList($newList);
    }
}