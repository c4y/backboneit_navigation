
<!-- indexer::stop -->
<div class="<?php echo $this->class; ?> block"<?php echo $this->cssID; ?><?php if ($this->style): ?> style="<?php echo $this->style; ?>"<?php endif; ?>>
<?php if ($this->headline): ?>

<<?php echo $this->hl; ?>><?php echo $this->headline; ?></<?php echo $this->hl; ?>>
<?php endif; ?>

<a href="<?php echo $this->request; ?>#<?php echo $this->skipId; ?>" class="invisible"><?php echo specialchars($GLOBALS['TL_LANG']['MSC']['skipNavigation']); ?></a>
<?php echo $this->items; ?> 
<a name="<?php echo $this->skipId; ?>" id="<?php echo $this->skipId; ?>" class="invisible">&nbsp;</a>

</div>
<!-- indexer::continue -->
