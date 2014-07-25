<?php

class CtiController extends Controller {

    /**
     * @create 2014.07.14
     * @todo CTI ON/OFF切り替え画面
     */
     public function actionIndex() {
        
        $reserveCti = new ReserveCti();
       
        if (isset($_POST['ReserveCti'])) {
            $reserveCti->attributes = $_POST['ReserveCti'];         
            $reserveCti->shop_id = Yii::app()->params['current_shop_id'];
            $reserveCti->operation = $_POST['ReserveCti']['operation'];

            $reserveCti->updateCtiOperation();
        }
        
        $reserveCti->operation = $reserveCti->confirmCtiOperation();
        $this->render('index', array(
            'model' => $reserveCti,
        ));
    }
}
