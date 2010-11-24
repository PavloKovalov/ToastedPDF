<?php
/**
 * Invoice2pdfModel
 *
 * @author pavel
 */
class ToastedpdfModel extends Zend_Db_Table_Abstract {
	private $_shoppingConfigTable = 'shopping_config';
    
	public function __constructor(){

	}

	public function selectShoppingConfig(){
		$sql = $this->getAdapter()->select()->from($this->_shoppingConfigTable);
		return $this->getAdapter()->fetchPairs($sql);
	}
}