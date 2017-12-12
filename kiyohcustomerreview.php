<?php
/**
 * 2014 Interactivated.me
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License (AFL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/afl-3.0.php
 *
 * @author    Interactivated <contact@interactivated.me>
 * @copyright 2014 Interactivated.me
 * @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 */

if (!defined('_PS_VERSION_'))
    exit;
if (!defined('_MYSQL_ENGINE_')) {
    define('_MYSQL_ENGINE_', 'MyISAM');
}

require_once __DIR__.'/vendor/autoload.php'; // Autoload here for the module definition

use League\Flysystem\Adapter\Local;
use League\Flysystem\Filesystem;
use Cache\Adapter\Filesystem\FilesystemCachePool;

class KiyohCustomerReview extends Module
{
    private $html = '';
    private $query = '';
    private $query_group_by = '';
    private $option = '';
    private $id_country = '';
    private $config = array('CONNECTOR' => '', 'COMPANY_EMAIL' => '', 'COMPANY_ID' => '', 'DELAY' => 1, 'ORDER_STATUS' => '', 'SERVER' => '', 'DEBUG' => '', 'LANGUAGE' => '','SHOW_RATING'=>'0');

    private $cache;
    private $cache_ttl = 300; //the number of seconds in which the cached value will expire


    public function __construct()
    {
        $this->name = 'kiyohcustomerreview';
        $this->tab = 'advertising_marketing';
        $this->version = '1.2.5';
        $this->author = 'Interactivated.me';
        $this->need_instance = 0;
        $this->module_key = '5f10179e3d17156a29ba692b6dd640da';

        parent::__construct();

        $this->getPsVersion();

        $this->displayName = $this->l('KiyOh Customer Review');
        $this->description = $this->l('KiyOh.nl users can use this plug-in automatically collect customer reviews');
        $this->ps_versions_compliancy = array('min' => '1.4.0.0', 'max' => '1.7.99.99');
        $configs = unserialize(Configuration::get('KIYOH_SETTINGS'));
        if(!is_array($configs)){
            $configs = array();
        }
        $this->config = array_merge($this->config,$configs);

        if (!extension_loaded('curl'))
            $this->warning = $this->l('cURL extension must be enabled on your server to use this module.');

        if (isset($this->config['WARNING']) && $this->config['WARNING'])
            $this->warning = $this->config['WARNING'];
        if (_PS_VERSION_ < '1.5' && _PS_VERSION_ > '1.3')
            require(_PS_MODULE_DIR_ . $this->name . '/backward_compatibility/backward.php');
        if (_PS_VERSION_ < '1.4' && !class_exists('Context', false)) {
            require(_PS_MODULE_DIR_ . $this->name . '/backward_compatibility/Context.php');
            $this->context = Context::getContext();
        }

        $this->initCache();
    }

    private function getPsVersion()
    {
        return $this->psv = (float)Tools::substr(_PS_VERSION_, 0, 3);
    }

    public function install()
    {
        if (!parent::install())
            return false;
        if ($this->psv >= 1.5) {
            if (!$this->registerHook('actionOrderStatusUpdate'))
                return false;
        } elseif ($this->psv < 1.5) {
            if (!$this->registerHook('updateOrderStatus'))
                return false;
        }
        if (!in_array('curl', get_loaded_extensions())) {
            $this->_errors[] = $this->l('Unable to install the module (php5-curl required).');
            return false;
        }

        return Db::getInstance()->execute('
                    CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'kiyohcustomerreview` (
                            id_customer INTEGER UNSIGNED NOT NULL,
                            id_shop INTEGER UNSIGNED NOT NULL,
                            status VARCHAR(255) NOT NULL,
                            date_add DATETIME NOT NULL,
                            PRIMARY KEY(id_customer,id_shop)
                    ) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8')
        && $this->registerHook('displayNav')
        && $this->registerHook('displayNav2')
        && $this->registerHook('displayHeader')
        && $this->registerHook('hookModuleRoutes');
    }

    public function uninstall()
    {
        if (!parent::uninstall())
            return false;
        Configuration::deleteByName('KIYOH_SETTINGS');
        return (Db::getInstance()->execute('DROP TABLE IF EXISTS `' . _DB_PREFIX_ . 'kiyohcustomerreview`'));
    }

    public function hookDisplayHeader($params)
    {
        $this->context->controller->addCSS(($this->_path) . 'views/css/rating.css', 'all');
    }


    public function getContent()
    {
        $output = '<h2>Kiyoh Customer Review</h2>';
        if (Tools::isSubmit('submitKiyoh')) {
            $this->config = array(
                'CONNECTOR' => Tools::getValue('connector'),
                'COMPANY_EMAIL' => Tools::getValue('company_email'),
                'COMPANY_ID' => Tools::getValue('company_id'),
                'DELAY' => Tools::getValue('delay'),
                'ORDER_STATUS' => Tools::getValue('order_status'),
                'SERVER' => Tools::getValue('server'),
                'DEBUG' => Tools::getValue('debug'),
                'SHOW_RATING' => Tools::getValue('show_rating'),
                'WARNING' => '',
                'LANGUAGE' => Tools::getValue('language')
            );
            Configuration::updateValue('KIYOH_SETTINGS', serialize($this->config));

            $output .= '
                        <div class="conf confirm">
                                <img src="../img/admin/ok.gif" alt="" title="" />
                                ' . $this->l('Settings updated') . '
                        </div>';
        }

        return $output . $this->displayForm();
    }

    public function hookDisplayNav2()
    {
        return $this->hookDisplayNav();
    }

    public function hookDisplayNav()
    {
        $tpl = 'nav';
        if (!$this->isCached($tpl . '.tpl', $this->getCacheId())) {
            $cache_id = $this->getCacheId() . ':request';
            if (!Cache::isStored($cache_id)) {
                $data = $this->receiveData();
                Cache::store($cache_id, $data);
            }
            $data = Cache::retrieve($cache_id);

            if (isset($data['company']['total_score'])) {
                $rating = $data['company']['total_score'];
                $maxrating = '10';
                $url = $data['company']['url'];
                $reviews = $data['company']['total_reviews'];
                $show_rating = 'display:none;';
                if($this->config['SHOW_RATING']=='1'){
                    $show_rating = 'display:block;';
                }
                $this->smarty->assign(array(
                    'rating' => $rating,
                    'rating_percentage' => $rating * 10,
                    'maxrating' => $maxrating,
                    'url' => $url,
                    'reviews' => $reviews,
                    'show_rating'=>$show_rating
                ));
                return $this->display(__FILE__, $tpl . '.tpl', $this->getCacheId());
            } else {
                return '';
            }
        }
        return $this->display(__FILE__, $tpl . '.tpl', $this->getCacheId());
    }

    public function receiveData()
    {
        $connector = $this->config['CONNECTOR'];
        $company_id = $this->config['COMPANY_ID'];
        $kiyoh_server = $this->config['SERVER'];

        $file = 'https://www.'.$kiyoh_server.'/xml/recent_company_reviews.xml?connectorcode=' . $connector . '&company_id=' . $company_id;

	    $key = md5($file);
	    if ($data = $this->cache->get($key)) {
	    	return $data;
	    }

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $file);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        $output = curl_exec($ch);
        curl_close($ch);

        $doc = simplexml_load_string($output);
        $data = array();
        if ($doc) {
            $data = json_decode(json_encode($doc), TRUE);
	        $this->cache->set($key, $data, $this->cache_ttl);
        }

        return $data;
    }

    public function displayForm()
    {
        $output = '
		<form action="' . Tools::safeOutput($_SERVER['REQUEST_URI']) . '" method="post">
			<fieldset class="width2">
				<legend><img src="../img/admin/cog.gif" alt="" class="middle" />' . $this->l('Settings') . '</legend>

                                <label>' . $this->l('Module Version') . '</label>
				<div class="margin-form">
					<p>' . $this->version . '</p>
                                </div>
				<label>' . $this->l('Enter Connector') . '</label>
				<div class="margin-form">
					<input type="text" name="connector" value="' . Tools::safeOutput(Tools::getValue('connector', $this->config['CONNECTOR'])) . '" />
					<p class="clear">' . $this->l('Enter here the KiyOh Connector Code from your KiyOh Account.') . '</p>
                                </div>

                                <label>' . $this->l('Company Email') . '</label>
                                <div class="margin-form">
					<input type="text" name="company_email" value="' . Tools::safeOutput(Tools::getValue('company_email', $this->config['COMPANY_EMAIL'])) . '" />
					<p class="clear">' . $this->l('Enter here your "company email address" as registered in your KiyOh account. Not the "user email address"! ') . '</p>
                                </div>
                                <label>' . $this->l('Company Id') . '</label>
                                <div class="margin-form">
					<input type="text" name="company_id" value="' . Tools::safeOutput(Tools::getValue('company_id', $this->config['COMPANY_ID'])) . '" />
					<p class="clear">' . $this->l('Enter here your "company id" as registered in your KiyOh account') . '</p>
                                </div>

                                <label>' . $this->l('Enter delay') . '</label>
                                <div class="margin-form">
					<input type="text" name="delay" value="' . Tools::safeOutput(Tools::getValue('delay', $this->config['DELAY'])) . '" />
					<p class="clear">' . $this->l('Enter here the delay(number of days) after which you would like to send review invite email to your customer. This delay applies after customer event(order status change - to be selected at next option). You may enter 0 to send review invite email immediately after customer event(order status change).') . '</p>
                                </div>

                                ';
                $output .= $this->selectHtml(
                            array(
                                'title'=>$this->l('Show rating'),
                                'name'=>'show_rating',
                                'options'=>array(
                                    '0'=>$this->l('Hide'),
                                    '1'=>$this->l('Show'),
                                )
                            )
                        );
//                $output .= $this->selectHtml(
//                    array(
//                        'title'=>$this->l('Select Event'),
//                        'name'=>'kiyoh_event',
//                        'options'=>array(
//                            'shipping'=>$this->l('Shipping'),
//                            'purchase'=>$this->l('Purchase'),
//                            'order_status_change'=>$this->l('Order status change'),
//                        ),
//                        'notice'=> '<p class="clear">'.$this->l('Enter here the event after which you would like to send review invite email to your customer. Enter Shipping if your store sells products that need shipping. Enter Purchase if your store sells downloadable products(softwares).').'</p>'
//                    )
//                );
        $id_lang = $this->context->language->id;
        $states = OrderState::getOrderStates($id_lang);
        $options = array();
        foreach ($states as $state)
            $options[$state['id_order_state']] = $state['name'];

        $output .= $this->selectHtml(
            array(
                'title' => $this->l('Order Status Change Event'),
                'name' => 'order_status',
                'options' => $options,
                //'notice'=>
                'multiple' => 'multiple',
//                        'depends'=>array(
//                            'kiyoh_event'=>'order_status_change'
//                        )
                'notice' => '<p class="clear">' . $this->l('Enter here the event after which you would like to send review invite email to your customer.') . '</p>'
            )
        );
        unset($options);

        $output .= $this->selectHtml(
            array(
                'title' => $this->l('Select Server'),
                'name' => 'server',
                'options' => array(
                    'kiyoh.nl' => $this->l('Kiyoh Netherlands'),
                    'kiyoh.com' => $this->l('Kiyoh International'),
                ),
                //'notice'=>
            )
        );

        $output .= $this->selectHtml(
            array(
                'title' => $this->l('Debug'),
                'name' => 'debug',
                'options' => array(
                    '0' => $this->l('No'),
                    '1' => $this->l('Yes'),
                ),
                //'notice'=>
            )
        );
        $output .= $this->selectHtml(
            array(
                'title' => $this->l('Email template language'),
                'name' => 'language',
                'options' => array(
                    '' => '',
                    '1' => $this->l('Dutch (BE)'),
                    '2' => $this->l('French'),
                    '3' => $this->l('German'),
                    '4' => $this->l('English'),
                    '5' => $this->l('Netherlands'),
                    '6' => $this->l('Danish'),
                    '7' => $this->l('Hungarian'),
                    '8' => $this->l('Bulgarian'),
                    '9' => $this->l('Romanian'),
                    '10' => $this->l('Croatian'),
                    '11' => $this->l('Japanese'),
                    '12' => $this->l('Spanish'),
                    '13' => $this->l('Italian'),
                    '14' => $this->l('Portuguese'),
                    '15' => $this->l('Turkish'),
                    '16' => $this->l('Norwegian'),
                    '17' => $this->l('Swedish'),
                    '18' => $this->l('Finnish'),
                    '20' => $this->l('Brazilian Portuguese'),
                    '21' => $this->l('Polish'),
                    '22' => $this->l('Slovenian'),
                    '23' => $this->l('Chinese'),
                    '24' => $this->l('Russian'),
                    '25' => $this->l('Greek'),
                    '26' => $this->l('Czech'),
                    '29' => $this->l('Estonian'),
                    '31' => $this->l('Lithuanian'),
                    '33' => $this->l('Latvian'),
                    '35' => $this->l('Slovak')
                ),
            )
        );
        $output .= '
                <script type="text/javascript">
                jQuery(document).ready(function() {
                  jQuery("#kiyoh_server select[name=server]").on("change",function(){
                    if(jQuery(this).val()=="kiyoh.com"){
                        jQuery("#kiyoh_language").show();
                    } else {
                        jQuery("#kiyoh_language").hide();
                    }
                  });
                  jQuery("#kiyoh_server select[name=server]").trigger("change");
                });
                </script>
                ';
        $output .= '
				<div class="margin-form"><input type="submit" name="submitKiyoh" value="' . $this->l('Save') . '" class="button" /></div>
			</fieldset>
		</form>';

        return $output;
    }

    public function selectHtml(array $config)
    {
        $multiple = '';
        if (isset($config['multiple'])) {
            $multiple = $config['multiple'];
        }
        $html = '<div id="kiyoh_' . $config['name'] . '"><label for="' . $config['name'] . '">' . $config['title'] . '</label>
                        <div class="margin-form">
                            <select name="' . $config['name'] . ($multiple ? '[]' : '') . '" ' . $multiple . '>';
        $options = $config['options'];
        $tmp = $this->config[Tools::strtoupper($config['name'])];
        $config_value = Tools::getValue($config['name'], $tmp);

        foreach ($options as $key => $value) {
            $selected = '';
            if ($config_value && ($key == $config_value || $multiple && in_array($key, $config_value))) {
                $selected = ' selected';
            }
            $html .= '<option value="' . $key . '"' . $selected . '>' . $value . '</option>';
        }
        $html .= '</select>';
        if (isset($config['notice'])) {
            $html .= $config['notice'];
        }
        $html .= '</div>';
        /** if (isset($config['depends'])){
         * $name = $config['name'];
         * foreach ($config['depends'] as $dep=>$val){
         * $html .= '<script>'
         * . '//<![CDATA['."\n"
         * . ';(function(){'."\n"
         * . '  jQuery(\'#kiyoh_'.$name.'\').hide();'."\n"
         * . '  jQuery(\'#kiyoh_'.$dep.'\').on(\'change\',function(){'."\n"
         * . '    var tmp = jQuery(\'option:selected\',this).val();'."\n"
         * . '    if (tmp==\''.$val.'\'){'."\n"
         * . '      jQuery(\'#kiyoh_'.$name.'\').slideDown();'."\n"
         * . '    } else {'."\n"
         * . '      jQuery(\'#kiyoh_'.$name.'\').slideUp();'."\n"
         * . '    }'."\n"
         * . '  }).trigger(\'change\');'."\n"
         * . '})();'."\n"
         * . '//]]>'
         * . '</script>';
         * }
         * } **/
        $html .= '</div>';
        return $html;

    }

    public function hookActionOrderStatusUpdate($params)
    {
        //$event = $this->config['KIYOH_EVENT'];
        $dispatched_order_statuses = $this->config['ORDER_STATUS'];
        $object = $params['newOrderStatus'];
        $new_order_status = $object->id;
        if(!is_array($dispatched_order_statuses)){
            $dispatched_order_statuses = array();
        }
        //if ($event === 'order_status_change'){
        if (in_array($new_order_status, $dispatched_order_statuses)) {
            $this->sendRequest($params['id_order']);
        }
        //}
    }

    public function hookUpdateOrderStatus($params)
    {
        $this->hookActionOrderStatusUpdate($params);
    }

    protected function sendRequest($order_id)
    {
        $order = new Order((int)$order_id);
        if ($this->psv >= 1.5) {
            $customer = $order->getCustomer();
        } elseif ($this->psv < 1.5) {
            $customer = new Customer($order->id_customer);
        }

        $email = $customer->email;
        if (!isset($order->id_shop)) {
            $id_shop = 0;
        } else {
            $id_shop = $order->id_shop;
        }

        if ($this->isInvitationSent($customer->id, $id_shop)) {
            return false;//invitation was already send
        }
        $kiyoh_server = $this->config['SERVER'];
        $kiyoh_user = $this->config['COMPANY_EMAIL'];
        $kiyoh_connector = $this->config['CONNECTOR'];
        $kiyoh_delay = $this->config['DELAY'];
        $language = $this->config['LANGUAGE'];
        $kiyoh_action = 'sendInvitation';
        $lang_str = '';
        if ($kiyoh_server == 'kiyoh.com') {
            $lang_str = '&language=' . $language;
        }
        if (!$email || !$kiyoh_server || !$kiyoh_user || !$kiyoh_connector) return false;

        $url = 'https://www.' . $kiyoh_server . '/set.php?user=' . $kiyoh_user . '&connector=' . $kiyoh_connector . '&action=' . $kiyoh_action . '&targetMail=' . $email . '&delay=' . $kiyoh_delay . $lang_str;

        // create a new cURL resource
        $curl = curl_init();

        // set URL and other appropriate options
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_HEADER, false);
        curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($curl, CURLOPT_TIMEOUT, 10);
        // grab URL and pass it to the browser
        $response = curl_exec($curl);
        $err = curl_errno($curl);
        if (trim($response) !== 'OK') {
            $this->config['WARNING'] = trim($response);
            Configuration::updateValue('KIYOH_SETTINGS', serialize($this->config));
        }
        if (_PS_VERSION_ >= '1.4') {
            if ($err || $response !== 'OK' || $this->config['DEBUG']) {
                if (class_exists('PrestaShopLogger')) {
                    PrestaShopLogger::addLog('Curl Error:' . curl_error($curl) . '---Response:' . $response . '---Url:' . $url, 2, null, $this->name);
                } elseif (class_exists('Logger')) {
                    Logger::addLog('Curl Error:' . curl_error($curl) . '---Response:' . $response . '---Url:' . $url, 2, null, $this->name);
                }

            }
        }
        $result = true;
        if (!$err && $response == 'OK') {
            $this->setInvitationSent($customer->id, $id_shop);
        } else {
            $result = false;
        }
        curl_close($curl);
        return $result;
    }

    protected function isInvitationSent($customer_id, $id_shop)
    {
        $sql = 'SELECT status FROM `' . _DB_PREFIX_ . 'kiyohcustomerreview`
                            WHERE `id_customer` = ' . (int)$customer_id . ' AND `id_shop` = ' . (int)$id_shop;

        $result = Db::getInstance()->executeS($sql);
        if (count($result)) {
            return true;
        }
        return false;
    }

    protected function setInvitationSent($customer_id, $id_shop)
    {
        $sql = 'INSERT INTO `' . _DB_PREFIX_ . 'kiyohcustomerreview`
                            (`id_customer`, `status`, `id_shop`, `date_add`)
			VALUES(' . (int)$customer_id . ', \'sent\', ' . (int)$id_shop . ', NOW())';

        Db::getInstance()->executeS($sql);
    }

	public function hookModuleRoutes() {
		require_once __DIR__.'/vendor/autoload.php'; // And the autoload here to make our Composer classes available everywhere!
	}

    private function initCache() {
	    $filesystemAdapter = new Local(_PS_CACHE_DIR_ . 'cachefs');
	    $filesystem        = new Filesystem($filesystemAdapter);

	    $pool = new FilesystemCachePool($filesystem);
	    $pool->setFolder($this->name);

	    $this->cache = $pool;
    }
}
