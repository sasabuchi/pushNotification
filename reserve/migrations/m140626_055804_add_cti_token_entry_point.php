<?php

class m140626_055804_add_cti_token_entry_point extends DbMigration
{
	public function safeUp()
	{
		$this->addColumn('dtb_pos_udid', 'device_token', 'VARCHAR(255) COMMENT "push通知デバイストークン" AFTER `udid` ');
		$this->addColumn('dtb_baseinfo', 'cti_entry_point', 'VARCHAR(255) COMMENT "CTIエントリポイント" AFTER `shop_color` ');
	}

	public function safeDown()
	{
		$this->dropColumn('dtb_pos_udid', 'device_token');
		$this->dropColumn('dtb_baseinfo', 'cti_entry_point');
		return false;
	}

}