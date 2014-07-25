<?php
/* @var $this   CtiController */
/* @var $model  ReserveCti */
?>

<div class="form col-sm-12">

<?php $form=$this->beginWidget('ActiveForm', array(
    'id'=>'cti-form',
    'enableAjaxValidation'=>false,
)); ?>

    <?php echo $model->operation == 1 ? "ただいま CTIは稼働中です" : "ただいま CTIは停止中です"
        ?>
    </br></br>
    
    <div class="row">
        <?php echo $form->label($model,'operation'); ?>
        <?php echo $form->dropDownList($model, 'operation', array (
            0 => 'OFF',
            1 => 'ON',
            )); ?>
    </div>

    <div class="row buttons">
        <?php echo Html::submitButton('実行'); ?>
    </div>

<?php $this->endWidget(); ?>

</div><!-- form -->