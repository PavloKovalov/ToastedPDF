<?php
/**
 * Description of ToastedPDF
 *
 * @author pavel
 */
define('I2PDFPLUGINPATH', dirname(realpath(__FILE__)));
require_once I2PDFPLUGINPATH.'/system/ToastedpdfModel.php';

class Toastedpdf implements RCMS_Core_PluginInterface {
	public static $encoding = 'UTF-8';
	const TEXT_ALIGN_LEFT = 'left';
    const TEXT_ALIGN_RIGHT = 'right';
    const TEXT_ALIGN_CENTER = 'center';

	private $_sitePath			= null;
	private $_shoppingConfig	= null;
	private $_font				= array();
	private $_color				= array();

	private $_template			= null;
	private $_pdf				= null;

	public function __construct() {
		$this->_model = new ToastedpdfModel();
		$this->_sitePath = unserialize(Zend_Registry::get('config'))->website->website->path;
		$this->_shoppingConfig = $this->_model->selectShoppingConfig();

		//initial settings
		//font settings
		$this->_font['normal']		= Zend_Pdf_Font::fontWithName(Zend_Pdf_font::FONT_HELVETICA);
		$this->_font['bold']		= Zend_Pdf_Font::fontWithName(Zend_Pdf_font::FONT_HELVETICA_BOLD);
		$this->_font['size_s']		= 10;
		$this->_font['size_m']		= 12;
		$this->_font['size_l']		= 14;
		$this->_font['size_xl']		= 16;
		$this->_font['size_xxl']	= 20;
		$this->_font['size_title']	= 36;
		//colors settings
		$this->_color['text']			= Zend_Pdf_Color_Html::color('#000000');
		$this->_color['background']		= Zend_Pdf_Color_Html::color('#FFFFFF');
		$this->_color['highlight']		= Zend_Pdf_Color_Html::color('#0099FF');
		$this->_color['highlightBg']	= Zend_Pdf_Color_Html::color('#3c3c3c');
		$this->_color['phone']			= $this->_color['highlight'];
		$this->_color['email']			= $this->_color['highlight'];
		$this->_color['title']			= Zend_Pdf_Color_Html::color('#006699');

		$this->encoding	= 'UTF-8';
	}

	public function run($requestParams = array()) {
		if (empty ($requestParams)){
			return false;
		} else {
			$keys =  array('billingAddress', 'shippingAddress', 'taxes', 'cart', 'summary');
			foreach ($keys as $key) {
				if (!array_key_exists($key, $requestParams)) {
					return false;
				}
			}
			
			$this->_billingAddress	= $requestParams['billingAddress'];
			$this->_shippingAddress	= $requestParams['shippingAddress'];
			$this->_taxes			= $requestParams['taxes'];
			$this->_cart			= $requestParams['cart'];
			$this->_summary			= $requestParams['summary'];
			$pdf = $this->_generatePDF();
			return $pdf;
		}
		//$id = $requestParams['id'];
		//$this->generatePDF($id);
	}

	private function _generatePDF() {
		//page padding
		$padding	= 20;

		//creating new pdf-document
		$this->_pdf = new Zend_Pdf();

		//adding first page;
		$pdfPage = new Zend_Pdf_Page(Zend_Pdf_Page::SIZE_A4);
		$pdfPage->setFont($this->_font['normal'], $this->_font['size_m']);
		$pdfPage->setFillColor($this->_color['text']);
		$gridStep = $pdfPage->getWidth()/12;

		//drawing company logo image
		$companyLogo = $this->_sitePath.($this->_shoppingConfig['company_logo']?str_replace('/small/', '/medium/', $this->_shoppingConfig['company_logo']):'');
		if (is_file($companyLogo)){
			$img = null;
			$imgInfo = getimagesize($companyLogo);
			try{
				switch ($imgInfo['mime']){
					case 'image/png':
						$img = new Zend_Pdf_Resource_Image_Png($companyLogo);
						break;
					case 'image/jpeg':
						$img = new Zend_Pdf_Resource_Image_Jpeg($companyLogo);
						break;
					case 'image/tiff':
						$img = new Zend_Pdf_Resource_Image_Tiff($companyLogo);
						break;
					default:
						break;
				}
				if (is_object($img)){
					$imgHeight = $img->getPixelHeight()/2;
					$imgWidth  = $img->getPixelWidth()/2;
					$x1 = 1.5*$padding;
					$y2 = $pdfPage->getHeight()-1.5*$padding;
					$x2 = 1.5*$padding+$imgWidth;
					$y1 = $pdfPage->getHeight()-1.5*$padding-$imgHeight;
					$pdfPage->drawImage($img, $x1, $y1, $x2, $y2);
				}
			} catch (Exception $e){
				echo $e->getMessage();
			}
		}
		//drawing company address
		$shopAddress = array(
			'company'	=> $this->_shoppingConfig['company'],
			'address1'	=> $this->_shoppingConfig['address'],
			'address2'	=> $this->_shoppingConfig['address2'],
			'city'		=> $this->_shoppingConfig['city'],
			'state'		=> $this->_shoppingConfig['state'],
			'zip'		=> $this->_shoppingConfig['zip'],
			'country'	=> RCMS_Object_QuickConfig_QuickConfig::$worldCountries[$this->_shoppingConfig['country']],
			'phone'		=> $this->_shoppingConfig['phone'],
			'email'		=> $this->_shoppingConfig['email']
		);
		$y = $this->drawCompanyAddressBox($shopAddress, $pdfPage, $pdfPage->getWidth()-2*$padding, $pdfPage->getHeight()-2*$padding, self::TEXT_ALIGN_RIGHT);
		
		//drawing document title
		$y = $this->drawDocumentTitle($pdfPage, $pdfPage->getWidth()/2, $y+2*$this->_font['size_xxl'], self::TEXT_ALIGN_CENTER);

		//drawing frame for document data
		$pdfPage->setFillColor($this->_color['background']);
		$pdfPage->drawRectangle($padding, $y, $pdfPage->getWidth()-$padding, $padding);
		$this->templateStartY = $y;
		$pdfPage->setFillColor($this->_color['text']);

		$this->_pdf->pages['template'] = clone $pdfPage;
		
//		$y -= $padding;
//		$pdfPage->drawText('Order #'.$this->_summary['id'], 2*$padding, $y, self::_encoding);
//		$date = 'Date: '.date('d M Y', $this->_summary['date']);
//		$pdfPage->drawText($date, $pdfPage->getWidth()-2*$padding-self::getTextWidth($date, $pdfPage), $y, self::_encoding);
//		$y -= 0.2*$padding;
//		$pdfPage->setFillColor($this->_color['highlightBg']);
//		$pdfPage->drawLine(1.5*$padding, $y, $pdfPage->getWidth()-1.5*$padding, $y);
		
		$y -= $padding;

		//drawing sub-header
		$pdfPage->setFillColor($this->_color['title']);
		$pdfPage->drawText('Details', 4*$gridStep, $y, self::$encoding);
		$text = 'Order Summary';
		$pdfPage->drawText($text, 9*$gridStep, $y, self::$encoding);
		$y -= 0.2*$padding;
		$pdfPage->setLineColor($this->_color['title']);
		$pdfPage->drawLine(1.5*$padding, $y, 8*$gridStep-$padding, $y);
		$pdfPage->drawLine(8*$gridStep, $y, $pdfPage->getWidth()-1.5*$padding, $y);
		$y -= 0.8*$padding;
		//drawing order summary
		$values = array (
			'sub-total' => '500',
			'tax' => '20.5',
			'shipping' => '0',
			'total' => (500+20.5-47.99)
		);
		$y1 = $this->drawSummary($this->_summary, $pdfPage, 8*$gridStep, $y, $pdfPage->getWidth()-1.8*$padding,$this->_shoppingConfig['show-price-ati']==1?true:false);
		
		//drawing order details
		
		$pdfPage->setFillColor($this->_color['text']);
//		$pdfPage->setFont($this->_font['normal'], $this->_font['size_s']);
//		$pdfPage->drawText('Shipping: '.$this->_summary['shipping_type'].'.', $gridStep, $y, self::$encoding);
//		$pdfPage->drawText('Payment method: '.$this->_summary['payment_method'].'.', 4*$gridStep, $y, self::$encoding);
//
//		$y -= 1.2*$pdfPage->getFontSize();
		$pdfPage->setFont($this->_font['bold'], $this->_font['size_s']);
		$pdfPage->drawText('Shipped to:', $gridStep, $y, self::$encoding);
		$pdfPage->drawText('Billing address:', 4.5*$gridStep, $y, self::$encoding);
		$pdfPage->setFont($this->_font['normal'], $this->_font['size_m']);
		$y -= 1.6*$pdfPage->getFontSize();
		$this->drawAddressBox($this->_billingAddress, $pdfPage, 4.5*$gridStep, $y);
		$y = $this->drawAddressBox($this->_shippingAddress, $pdfPage, $gridStep, $y);
		$y = $y>$y1?$y1:$y;
		//drawing cart content
		$this->drawCartContent($this->_cart, $pdfPage, 2*$padding, $y, null, $this->_shoppingConfig['show-price-ati']==1?true:false, $this->_taxes['taxPerProduct']);

		unset($this->_pdf->pages['template']);
		if (empty($this->_pdf->pages)) {
			$this->_pdf->pages[] = $pdfPage;
		}
		$pagesTotal = count($this->_pdf->pages);
		$i = 1;
		foreach ($this->_pdf->pages as $pg){
			$text = 'Page '.$i++.' of '.$pagesTotal;
			$pg->drawText($text, $pg->getWidth()-$padding-self::getTextWidth($text, $pg), $this->templateStartY+5);
		}
		$result = $this->_pdf->render();
		return $result;
	}

	private function drawAddressBox($address, Zend_Pdf_Page $page, $x, $y, $align = self::TEXT_ALIGN_LEFT, $lineHeight = 1.2) {
		
		$lineHeight = $lineHeight * $page->getFontSize();

		if (isset($address['firstname'])) {
			$text = $address['firstname'] . ($address['lastname']?' '.$address['lastname']:'');
			$page->drawText($text, $x, $y, self::$encoding);
			$y -= $lineHeight;
		}
		if (isset($address['company'])) {
			$page->drawText($address['company'], $x, $y, self::$encoding);
			$y -= $lineHeight;
		}
		if (isset($address['address1'])) {
			$text = $address['address1'].($address['address2']?', '.$address['address2']:'');
			$page->drawText($text, $x, $y, self::$encoding);
			$y -= $lineHeight;
		}
		if (isset($address['city'])) {
			$text = $address['city'].($address['state']?', '.$address['state']:'').($address['zip']?', '.$address['zip']:'');
			$page->drawText($text, $x, $y, self::$encoding);
			$y -= $lineHeight;
		}
		if (isset($address['country'])) {
			$page->drawText(RCMS_Object_QuickConfig_QuickConfig::$worldCountries[$address['country']], $x, $y, self::$encoding);
			$y -= $lineHeight;
		}
		if (isset($address['phone'])) {
			$page->drawText('Phone: '.$address['phone'], $x, $y, self::$encoding);
			$y -= $lineHeight;
		}
		if (isset($address['email'])) {
			$page->drawText($address['email'], $x, $y, self::$encoding);
			$y -= $lineHeight;
		}
		
		return $y-=$lineHeight;
	}

	private function drawCompanyAddressBox($address, Zend_Pdf_Page $page, $x, $y, $align = self::TEXT_ALIGN_LEFT, $lineHeight = 1.2) {
		$lineHeight = $lineHeight * $page->getFontSize();
		switch ($align) {
			case self::TEXT_ALIGN_CENTER:
				$posMarker = 0.5;
				break;
			case self::TEXT_ALIGN_RIGHT:
				$posMarker = 1;
				break;
			case self::TEXT_ALIGN_LEFT:
			default:
				$posMarker = 0;
				break;
		}

		if (!empty ($address['company'])) {
			$page->setFont($this->_font['bold'], $this->_font['size_l']);
			$page->drawText($address['company'], $x-($posMarker*self::getTextWidth($address['company'], $page)), $y, $this->encoding);
			$page->setFont($this->_font['normal'], $this->_font['size_m']);
			$y -= $lineHeight*1.2;
		}
		if (!empty ($address['address1'])) {
			$text = $address['address1'].(!empty($address['address2'])?', '.$address['address2']:'');
			$page->drawText($text, $x-$posMarker*self::getTextWidth($text, $page), $y, $this->encoding);
			$y -= $lineHeight;
		}
		if (!empty ($address['city'])) {
			$text = $address['city'].(!empty($address['state'])||!empty($address['zip'])?', '.$address['state']:'').($address['zip']?' '.$address['zip']:'');
			$page->drawText($text, $x-$posMarker*self::getTextWidth($text, $page), $y, $this->encoding);
			$y -= $lineHeight;
		}
		if (!empty ($address['country'])) {
			$text = $address['country'];
			$page->drawText($text, $x-$posMarker*self::getTextWidth($text, $page), $y, $this->encoding);
			$y -= $lineHeight;
		}
		if (!empty ($address['phone'])) {
			$text = 'Phone: '.$address['phone'];
			$page->setFillColor($this->_color['phone']);
			$page->drawText($text, $x-$posMarker*self::getTextWidth($text, $page), $y, $this->encoding);
			$y -= $lineHeight;
		}
		if (!empty ($address['email'])) {
			$text = $address['email'];
			$page->setFillColor($this->_color['phone']);
			$page->drawText($text, $x-$posMarker*self::getTextWidth($text, $page), $y, $this->encoding);
			$y -= $lineHeight;
		}
		$page->setFillColor($this->_color['text']);
		return $y-=$lineHeight;
	}

	private function drawCartContent($cartContent, Zend_Pdf_Page $page, $x, $y, $width = null, $pricesIncTax = false, $taxes = null){
		$lineHeight = 3.6*$page->getFontSize();
		$txtPadding = round($page->getFontSize()/2);
		if (is_array($cartContent) && !empty ($cartContent)) {
//			if ($pricesIncTax){
//				$colsNum = 7;
//			} else {
//				$colsNum = 8;
//			}
			$colsNum = 6;
			$colsWidth = floor((null !== $width ? $width-$x : $page->getWidth()-2*$x) / $colsNum) ;

			$y = $this->drawCartHeader($page, $x, $y, $colsWidth);

			foreach ($cartContent as $key => $item) {
				//checking if enough place on page left
				if ($y<$lineHeight) {
					$this->_pdf->pages[] = $page;
					$page = clone $this->_pdf->pages['template'];
					$page->setFont($this->_font['normal'], $this->_font['size_m']);
					$y = $this->templateStartY-$lineHeight;
					$y = $this->drawCartHeader($page, $x, $y, $colsWidth);
					$newPage = true;
				}
				//drawing product image
				if (!empty($item['photo'])){
					$img = null;
					$filename = $this->_sitePath.$item['photo'];
					if (is_file($filename)){
						$imgInfo = getimagesize($filename);
						try{
							switch ($imgInfo['mime']){
								case 'image/png':
									$img = new Zend_Pdf_Resource_Image_Png($filename);
									break;
								case 'image/jpeg':
									$img = new Zend_Pdf_Resource_Image_Jpeg($filename);
									break;
								case 'image/tiff':
									$img = new Zend_Pdf_Resource_Image_Tiff($filename);
									break;
								default:
									break;
							}
							if (is_object($img)){
								$imgHeight = round(0.8*$lineHeight);
								$imgWidth  = $img->getPixelWidth()*($imgHeight/$img->getPixelHeight());
								$x1 = $x;
								$y1 = $y-$imgHeight/2;
								$x2 = $x+$imgWidth;
								$y2 = $y+$imgHeight/2;
								$page->drawImage($img, $x1, $y1, $x2, $y2);
							}
						} catch (Exception $e){
							echo $e->getMessage();
						}

					}
				}
				//item id
				$page->setFont($page->getFont(), $this->_font['size_s']);
				$text = $item['itemId'];
				$page->drawText($text, 1.25*$colsWidth, $y, self::$encoding);

				$page->setFont($page->getFont(), $this->_font['size_m']);
				$text = $item['name'];
				$textWidth = self::getTextWidth($text, $page);
				if ( $textWidth > $colsWidth*2+$x) {
					$words = explode(' ', $text);
					$str = '';
					while (self::getTextWidth($str, $page) < 2.25*$colsWidth) {
					  $str .= current($words) .' ';
					  next($words);
					}
					$page->drawText($str.'...', $colsWidth*2, $y, self::$encoding);
				} else {
					$page->drawText($text, $colsWidth*2, $y, self::$encoding);
				}
				if (isset ($item['options'])){
					$y1 = $y - $page->getFontSize();
					foreach ($item['options'] as $name=>$value){
						$page->setFont($this->_font['normal'], $this->_font['size_s']);
						$page->drawText(ucfirst($name).': '.ucfirst($value), $x+$colsWidth*2, $y1, self::$encoding);
						$y1 -= 1.2*$page->getFontSize();
					}
					$page->setFont($this->_font['normal'], $this->_font['size_m']);
				}
				
				//$page->drawText($item['note'], $x+$colsWidth*3, $y, self::$encoding);

				$price = $item['price']+($pricesIncTax&&isset($taxes[$item['id']])?$taxes[$item['id']]:0);
				$text = $price>0?number_format($price,2,'.',''):'FREE';
				$x1 = $x+$colsWidth*4.5-self::getTextWidth($text, $page)/2;
				$page->drawText($text, $x1, $y, self::$encoding);

				$text = $item['count'];
				$x1 = $x+$colsWidth*5.25-self::getTextWidth($text, $page);
				$page->drawText($text, $x1, $y, self::$encoding);

				$text = $price>0?number_format(($item['count']*$price),2,'.',''):'FREE';
				$x1 = $x+$colsWidth*5.75-self::getTextWidth($text, $page)/2;
				$page->drawText($text, $x1, $y, self::$encoding);
				
				$y -= $lineHeight;
			}
//			$text = $this->_summary['subtotal'].' '.$this->_shoppingConfig['currency'];
//			$page->drawText($text, $colsWidth*$colsNum-self::getTextWidth($text, $page), $y);
//			$y -= $lineHeight;
			if (isset($newPage) && ($newPage === true)) $this->_pdf->pages[] = $page;
		}
	}
	private function drawCartHeader(Zend_Pdf_Page $page, $x, $y, $colWidth, $withTax = null) {
		$lineHeight = 2*$page->getFontSize();
		
		$page->setFont($this->_font['normal'], $this->_font['size_s']-2);
		$text = 'Photo';
		$x1 = $x+0.25*$colWidth;//-self::getTextWidth($text, $page)/2;
		$page->drawText($text, $x1, $y, self::$encoding);

		$text = 'SKU';
		$x1 = $x+$colWidth;
		$page->drawText($text, $x1, $y, self::$encoding);

		$text = 'Name';
		$x1 = $x+3*$colWidth;
		$page->drawText($text, $x1, $y, self::$encoding);

		$text = 'Price ('.$this->_shoppingConfig['currency'].')';
		$x1 = $x+4.5*$colWidth-self::getTextWidth($text, $page)/2;
		$page->drawText($text, $x1, $y, self::$encoding);

		$text = 'Qty';
		$x1 = $x+5.2*$colWidth-self::getTextWidth($text, $page)/2;
		$page->drawText($text, $x1, $y, self::$encoding);

		$text = 'Total ('.$this->_shoppingConfig['currency'].')';
		$x1 = $x+5.75*$colWidth-self::getTextWidth($text, $page)/2;
		$page->drawText($text, $x1, $y, self::$encoding);
		$page->setLineColor($this->_color['highlightBg']);
		$page->setLineWidth(0.5);
		$page->drawLine($x, $y-0.1*$lineHeight, $page->getWidth()-$x, $y-0.1*$lineHeight);
		return $y-$lineHeight;
	}

	private function drawSummary($values, Zend_Pdf_Page $page, $x, $y, $x1, $withTax = false){
		$page->setFont($this->_font['normal'], $this->_font['size_m']);
		$page->setFillColor($this->_color['text']);
		$lineHeight = 1.5*$page->getFontSize();
		$x += 5;
		if (is_array($values) && !empty ($values)) {
			if (isset ($values['subtotal'])){
				$label = $withTax?'Sub-total' : 'Total w/o tax:';
				$page->drawText($label, $x, $y, self::$encoding);
				$price = $withTax?$values['subtotal']+$values['tax']:$values['subtotal'];
				$price = number_format($price,2,'.','').' '.$this->_shoppingConfig['currency'];
				$page->drawText($price, $x1-self::getTextWidth($price, $page), $y, self::$encoding);
				$y -= $lineHeight;
			}

			if (isset ($values['shipping'])){
				$label = 'Shipping:';
				$page->drawText($label, $x, $y, self::$encoding);
				$price = !empty ($values['shipping'])?number_format($values['shipping'],2,'.','').' '.$this->_shoppingConfig['currency']:'FREE';
				$page->drawText($price, $x1-self::getTextWidth($price, $page), $y, self::$encoding);
				$y -= $lineHeight;
			}

			if (isset ($values['discount']) && $values['discount']>0){
				$label = 'Discount:';
				$page->drawText($label, $x, $y, self::$encoding);
				$price = $values['discount'];
				$price = number_format($price,2,'.','').' '.$this->_shoppingConfig['currency'];
				$page->drawText($price, $x1-self::getTextWidth($price, $page), $y, self::$encoding);
				$y -= $lineHeight;
			}
			
			if (!$withTax && isset ($values['tax'])){
				$label = 'Tax:';
				$page->drawText($label, $x, $y, self::$encoding);
				$price = $values['tax']>0?$values['tax']:'0.00';
				$price = number_format($price,2,'.','').' '.$this->_shoppingConfig['currency'];
				$page->drawText($price, $x1-self::getTextWidth($price, $page), $y, self::$encoding);
				$y -= $lineHeight;
			}
			if (isset ($values['total'])){
				$page->setFillColor($this->_color['title']);
				$label = $withTax?'Total:':'Total inc. tax:';
				$page->drawText($label, $x, $y, self::$encoding);
				$xLabelEnd = $x+10+self::getTextWidth($label, $page);
				$price = $values['total'];
				$price = number_format($price,2,'.','').' '.$this->_shoppingConfig['currency'];
				$page->setFont($this->_font['bold'], $this->_font['size_l']);
				$xPrice = $x1-self::getTextWidth($price, $page);
				$y -= $xPrice<$xLabelEnd? $lineHeight: 0;
				$page->drawText($price, $xPrice, $y, self::$encoding);
				$y -= $lineHeight;
				$page->setFillColor($this->_color['text']);
			}
			if ($withTax && isset ($values['tax'])){
				$page->setFont($this->_font['normal'],$this->_font['size_m']);
				$label = 'Inc.Tax';
				$page->drawText($label, $x, $y, self::$encoding);
				$price = $values['tax']>0?$values['tax']:'0.00';
				$price = number_format($price,2,'.','').' '.$this->_shoppingConfig['currency'];
				$page->drawText($price, $x1-self::getTextWidth($price, $page), $y, self::$encoding);
				$y -= $lineHeight;
			}
			if ($withTax){
				$page->setFont($this->_font['normal'],$this->_font['size_m']);
				$label = 'Total w/o Tax:';
				$page->drawText($label, $x, $y, self::$encoding);
				$price = $values['total'] - $values['tax'];
				$price = number_format($price,2,'.','').' '.$this->_shoppingConfig['currency'];
				$page->drawText($price, $x1-self::getTextWidth($price, $page), $y, self::$encoding);
				$y -= $lineHeight;
			}


			if (isset ($values['shipping_type'])){
				$page->setFont($this->_font['normal'],$this->_font['size_m']);
				$label = 'Shipping type:';
				$page->drawText($label, $x, $y, self::$encoding);
				$text = $values['shipping_type'];
				$page->drawText($text, $x1-self::getTextWidth($text, $page), $y, self::$encoding);
				$y -= $lineHeight;
			}

			if (isset ($values['payment_method'])){
				$page->setFont($this->_font['normal'],$this->_font['size_m']);
				$label = 'Payment method:';
				$page->drawText($label, $x, $y, self::$encoding);
				$text = $values['payment_method'];
				$page->drawText($text, $x1-self::getTextWidth($text, $page), $y, self::$encoding);
				$y -= $lineHeight;
			}

			$page->setFont($this->_font['normal'],$this->_font['size_m']);
			return $y-$lineHeight;
		}
		return $y;
	}
	private function drawDocumentTitle(Zend_Pdf_Page $page, $x, $y, $align = self::TEXT_ALIGN_LEFT, $lineHeight = 1.1) {
		
		switch ($align) {
			case self::TEXT_ALIGN_CENTER:
				$posMarker = 0.5;
				break;
			case self::TEXT_ALIGN_RIGHT:
				$posMarker = 1;
				break;
			case self::TEXT_ALIGN_LEFT:
			default:
				$posMarker = 0;
				break;
		}
		
		$page->setFillColor($this->_color['title']);
		
		if (!empty ($this->_summary['title'])){
			$page->setFont($this->_font['bold'], $this->_font['size_title']);
			$x1 = round($x-($posMarker*self::getTextWidth($this->_summary['title'], $page)));
			$page->drawText(ucfirst($this->_summary['title']), $x1, $y, self::$encoding);
			$y -= $lineHeight * $page->getFontSize();
		}
		if (!empty ($this->_summary['id'])){
			$page->setFont($this->_font['bold'], $this->_font['size_xxl']);
			$text = '# '.$this->_summary['id'];
			$x1 = round($x-($posMarker*self::getTextWidth($text, $page)));
			$page->drawText($text, $x1, $y, self::$encoding);
			$y -= $lineHeight * $page->getFontSize();
		}
		if (!empty ($this->_summary['date'])){
			$page->setFont($this->_font['bold'], $this->_font['size_xxl']);
			$text = date('d M Y', $this->_summary['date']);
			$x1 = round($x-($posMarker*self::getTextWidth($text, $page)));
			$page->drawText($text, $x1, $y, self::$encoding);
			$y -= $lineHeight * $page->getFontSize();
		}
		$page->setFont ($this->_font['normal'], $this->_font['size_m']);
		$page->setFillColor($this->_color['text']);
		return $y;
	}

	public static function getTextWidth($text, Zend_Pdf_Page $page, $fontSize = null, $encoding = null) {
        if( $encoding == null ) $encoding = self::$encoding;

        if( $page instanceof Zend_Pdf_Page ){
            $font = $page->getFont();
            $fontSize = $page->getFontSize();
        }elseif( $page instanceof Zend_Pdf_Resource_Font ){
            $font = $page;
            if( $fontSize === null ) throw new Exception('The fontsize is unknown');
        }

        if( !$font instanceof Zend_Pdf_Resource_Font ){
            throw new Exception('Invalid resource passed');
        }

        $drawingText = iconv ( '', $encoding, $text );
        $characters = array ();
        for($i = 0; $i < strlen ( $drawingText ); $i ++) {
            $characters [] = ord ( $drawingText [$i] );
        }
        $glyphs = $font->glyphNumbersForCharacters ( $characters );
        $widths = $font->widthsForGlyphs ( $glyphs );
        $textWidth = (array_sum ( $widths ) / $font->getUnitsPerEm ()) * $fontSize;
        return $textWidth;
    }

}
