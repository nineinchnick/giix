<?php
/**
 * The following variables are available in this template:
 * - $this: the CrudCode object
 */
?>
<div class="wide form">

<?php echo "<?php \$form = \$this->beginWidget('bootstrap.widgets.TbActiveForm', array(
	'action' => Yii::app()->createUrl(\$this->route),
	'method' => 'get',
)); ?>\n"; ?>

<?php foreach($this->tableSchema->columns as $column): ?>
<?php
	$field = $this->generateInputField($this->modelClass, $column);
	if (strpos($field, 'password') !== false)
		continue;
?>
	<?php echo "<?php echo " . $this->generateActiveControlGroup($this->modelClass, $column) . "; ?>\n"; ?>

<?php endforeach; ?>
    <div class="form-actions">
        <?php echo "<?php echo TbHtml::submitButton(Yii::t('app', 'Search'),  array('color' => TbHtml::BUTTON_COLOR_PRIMARY,));?>\n" ?>
    </div>

<?php echo "<?php \$this->endWidget(); ?>\n"; ?>

</div><!-- search-form -->