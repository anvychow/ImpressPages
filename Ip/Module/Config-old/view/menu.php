<?php
/**
 * This comment block is used just to make IDE suggestions to work
 * @var $items \Ip\Menu\Item[]
 * @var $this \Ip\View
 */
?>
<?php if (isset($items[0])){?>
    <?php $firstItem = $items[0]; ?>
    <ul class="level<?php echo $depth ?>">
        <?php foreach($items as $item){ ?>
            <?php echo $this->subview('menuItem.php', array('menuItem' => $item, 'depth' => $depth))->render() ?>
        <?php } ?>
    </ul>
<?php } ?>