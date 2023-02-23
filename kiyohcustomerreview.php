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
if (!defined('_PS_VERSION_')) {
    exit;
}
if (!defined('_MYSQL_ENGINE_')) {
    define('_MYSQL_ENGINE_', 'MyISAM');
}

class KiyohCustomerReview extends Module
{
    private $html = '';
    private $query = '';
    private $query_group_by = '';
    private $option = '';
    protected $price = 0;
    private $id_country = '';
    private $config = [
        'CONNECTOR' => '',
        'COMPANY_EMAIL' => '',
        'COMPANY_ID' => '',
        'DELAY' => 1,
        'ORDER_STATUS' => '',
        'SERVER' => '',
        'DEBUG' => '',
        'LANGUAGE' => '',
        'SHOW_RATING' => '0',
        'LANGUAGE1' => 'nl',
        'HASH' => '',
        'LOCATIONID' => '',
    ];

    /** @var \Cache\Adapter\Filesystem\FilesystemCachePool */
    private $cache;
    private $cache_ttl = 300; // the number of seconds in which the cached value will expire

    public function __construct()
    {
        $this->name = 'kiyohcustomerreview';
        $this->tab = 'advertising_marketing';
        $this->version = '1.3.13';
        $this->author = 'Interactivated.me';
        $this->need_instance = 0;
        $this->module_key = '5f10179e3d17156a29ba692b6dd640da';

        parent::__construct();

        $this->getPsVersion();

        $this->displayName = $this->l('KiyOh Customer Review');
        $this->description = $this->l('KiyOh.nl users can use this plug-in automatically collect customer reviews');
        $this->ps_versions_compliancy = ['min' => '1.4.0.0', 'max' => _PS_VERSION_];
        $configs = unserialize(Configuration::get('KIYOH_SETTINGS'));
        if (!is_array($configs)) {
            $configs = [];
        }
        $this->config = array_merge($this->config, $configs);

        if (!extension_loaded('curl')) {
            $this->warning = $this->l('cURL extension must be enabled on your server to use this module.');
        }

        if (isset($this->config['WARNING']) && $this->config['WARNING']) {
            $this->warning = $this->config['WARNING'];
        }
        $this->initCache();
    }

    private function getPsVersion()
    {
        return $this->psv = (float) Tools::substr(_PS_VERSION_, 0, 3);
    }

    public function install()
    {
        if (!parent::install()) {
            return false;
        }
        if ($this->psv >= 1.5) {
            if (!$this->registerHook('actionOrderStatusUpdate')) {
                return false;
            }
        } elseif ($this->psv < 1.5) {
            if (!$this->registerHook('updateOrderStatus')) {
                return false;
            }
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
        if (!parent::uninstall()) {
            return false;
        }
        Configuration::deleteByName('KIYOH_SETTINGS');

        return Db::getInstance()->execute('DROP TABLE IF EXISTS `' . _DB_PREFIX_ . 'kiyohcustomerreview`');
    }

    public function hookDisplayHeader($params)
    {
        $this->context->controller->addCSS($this->_path . 'views/css/rating.css', 'all');
    }

    public function getContent()
    {
        $output = '<h2>' . $this->l('Kiyoh Customer Review') . '</h2>';
        if (Tools::isSubmit('submitKiyoh')) {
            $this->config = [
                'CONNECTOR' => Tools::getValue('connector'),
                'COMPANY_EMAIL' => Tools::getValue('company_email'),
                'COMPANY_ID' => Tools::getValue('company_id'),
                'DELAY' => Tools::getValue('delay'),
                'ORDER_STATUS' => Tools::getValue('order_status'),
                'SERVER' => Tools::getValue('server'),
                'DEBUG' => Tools::getValue('debug'),
                'SHOW_RATING' => Tools::getValue('show_rating'),
                'WARNING' => '',
                'LANGUAGE' => Tools::getValue('language'),
                'LANGUAGE1' => Tools::getValue('language1'),
                'HASH' => Tools::getValue('hash'),
                'LOCATIONID' => Tools::getValue('locationid'),
            ];
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
                if ($this->config['SHOW_RATING'] == '1') {
                    $show_rating = 'display:block;';
                }
                $this->smarty->assign([
                    'storename' => Configuration::get('PS_SHOP_NAME'),
                    'rating' => $rating,
                    'rating_percentage' => $rating * 10,
                    'maxrating' => $maxrating,
                    'url' => $url,
                    'reviews' => $reviews,
                    'show_rating' => $show_rating,
                ]);

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

        $hash = '';
        if ($kiyoh_server == 'klantenvertellen.nl' || $kiyoh_server == 'newkiyoh.com') {
            $server = 'klantenvertellen.nl';
            if ($kiyoh_server == 'newkiyoh.com') {
                $server = 'kiyoh.com';
            }
            $location_id = $this->config['LOCATIONID'];
            $hash = $this->config['HASH'];
            $file = "https://{$server}/v1/publication/review/external/location/statistics?locationId=" . $location_id;
        } else {
            $file = 'https://www.' . $kiyoh_server . '/xml/recent_company_reviews.xml?connectorcode=' . $connector . '&company_id=' . $company_id;
        }

        $key = md5($file);
        if ($this->cache && $data = $this->cache->get($key)) {
            return $data;
        }
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $file);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 1);
        if ($kiyoh_server == 'klantenvertellen.nl' || $kiyoh_server == 'newkiyoh.com') {
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'X-Publication-Api-Token: ' . $hash,
            ]);
        }
        $output = curl_exec($ch);
        curl_close($ch);

        $doc = '';
        if ($kiyoh_server == 'klantenvertellen.nl' || $kiyoh_server == 'newkiyoh.com') {
            $datajson = json_decode($output, true);
            $dataxml = new \stdClass();
            $company = new \stdClass();
            if (isset($datajson['averageRating'])) {
                $company->total_score = $datajson['averageRating'];
                $company->url = $datajson['viewReviewUrl'];
                $company->total_reviews = $datajson['numberReviews'];
                $dataxml->company = $company;
            }

            $doc = $dataxml;
        } else {
            $doc = simplexml_load_string($output);
        }

        $data = [];
        if ($doc && $this->cache) {
            $data = json_decode(json_encode($doc), true);
            $this->cache->set($key, $data, $this->cache_ttl);
        }
        if (!isset($data['company']['total_score']) && $this->cache) {
            $this->cache->clear();
        }

        return $data;
    }

    public function displayForm()
    {
        $id_lang = $this->context->language->id;
        $states = OrderState::getOrderStates($id_lang);
        $order_statuses = [];
        foreach ($states as $state) {
            $order_statuses[$state['id_order_state']] = $state['name'];
        }
        $this->smarty->assign([
            'current_url' => $this->context->link->getAdminLink('AdminModules') . '&configure=kiyohcustomerreview&tab_module=advertising_marketing&module_name=kiyohcustomerreview',
            'request_uri' => Tools::safeOutput($_SERVER['REQUEST_URI']),
            'version' => $this->version,
            'connector' => Tools::safeOutput(Tools::getValue('connector', $this->config['CONNECTOR'])),
            'company_email' => Tools::safeOutput(Tools::getValue('company_email', $this->config['COMPANY_EMAIL'])),
            'company_id' => Tools::safeOutput(Tools::getValue('company_id', $this->config['COMPANY_ID'])),
            'delay' => Tools::safeOutput(Tools::getValue('delay', $this->config['DELAY'])),
            'show_rating' => Tools::safeOutput(Tools::getValue('show_rating', $this->config['SHOW_RATING'])),
            'show_rating_aval' => ['0' => $this->l('Hide'), '1' => $this->l('Show')],
            'allorder_statuses' => $order_statuses,
            'order_status' => Tools::getValue('order_status', $this->config['ORDER_STATUS']),
            'servers' => [
                'klantenvertellen.nl' => $this->l('New Klantenvertellen.nl'),
                'newkiyoh.com' => $this->l('New Kiyoh.com'),
                'kiyoh.nl' => $this->l('Old Kiyoh.nl'),
                'kiyoh.com' => $this->l('Old Kiyoh.com'),
            ],
            'server' => Tools::getValue('server', $this->config['SERVER']),
            'langs' => [
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
                '35' => $this->l('Slovak'),
            ],
            'language' => Tools::getValue('language', $this->config['LANGUAGE']),
            'language1' => Tools::getValue('language1', $this->config['LANGUAGE1']),
            'hash' => Tools::getValue('hash', $this->config['HASH']),
            'locationid' => Tools::getValue('locationid', $this->config['LOCATIONID']),
        ]);
        $output = $this->display(__FILE__, 'adminsettings.tpl');

        return $output;
    }

    public function hookActionOrderStatusUpdate($params)
    {
        $dispatched_order_statuses = $this->config['ORDER_STATUS'];
        $object = $params['newOrderStatus'];
        $new_order_status = $object->id;
        if (!is_array($dispatched_order_statuses)) {
            $dispatched_order_statuses = [];
        }
        if (in_array($new_order_status, $dispatched_order_statuses)) {
            $this->sendRequest($params['id_order']);
        }
    }

    public function hookUpdateOrderStatus($params)
    {
        $this->hookActionOrderStatusUpdate($params);
    }

    protected function sendRequest($order_id)
    {
        $order = new Order((int) $order_id);
        $firstname = '';
        $lastname = '';
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
            return false; // invitation was already send
        }
        $kiyoh_server = $this->config['SERVER'];
        if ($kiyoh_server == 'kiyoh.com' || $kiyoh_server == 'kiyoh.nl') {
            $kiyoh_user = $this->config['COMPANY_EMAIL'];
            $kiyoh_connector = $this->config['CONNECTOR'];
            $kiyoh_delay = $this->config['DELAY'];
            $language = $this->config['LANGUAGE'];
            $kiyoh_action = 'sendInvitation';
            $lang_str = '';

            if (!$email || !$kiyoh_server || !$kiyoh_user || !$kiyoh_connector) {
                return false;
            }
            $vars = [
                'user' => $kiyoh_user,
                'connector' => $kiyoh_connector,
                'action' => $kiyoh_action,
                'targetMail' => $email,
                'delay' => $kiyoh_delay,
            ];
            if ($kiyoh_server == 'kiyoh.com') {
                $vars['language'] = $language;
            }
            $url = 'https://www.' . $kiyoh_server . '/set.php?' . http_build_query($vars);
        } else {
            $hash = $this->config['HASH'];
            $kiyoh_delay = $this->config['DELAY'];
            $location_id = $this->config['LOCATIONID'];
            $language_1 = $this->config['LANGUAGE1'];
            $first_name = urlencode($customer->firstname);
            $last_name = urlencode($customer->lastname);

            $server = 'klantenvertellen.nl';
            if ($kiyoh_server == 'newkiyoh.com') {
                $server = 'kiyoh.com';
            }
            $vars = [
                'hash' => $hash,
                'location_id' => $location_id,
                'invite_email' => $email,
                'delay' => $kiyoh_delay,
                'first_name' => $first_name,
                'last_name' => $last_name,
                'language' => $language_1,
            ];
            $url = "https://{$server}/v1/invite/external?" . http_build_query($vars);
        }
        // create a new cURL resource
        $curl = curl_init();
        // set URL and other appropriate options
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_HEADER, false);
        curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 2);
        curl_setopt($curl, CURLOPT_TIMEOUT, 2);
        // grab URL and pass it to the browser
        $response = curl_exec($curl);
        $err = curl_errno($curl);
        if (trim($response) !== 'OK' && ($kiyoh_server == 'kiyoh.com' || $kiyoh_server == 'kiyoh.nl')) {
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
                            WHERE `id_customer` = ' . (int) $customer_id . ' AND `id_shop` = ' . (int) $id_shop;
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
			VALUES(' . (int) $customer_id . ', \'sent\', ' . (int) $id_shop . ', NOW())';
        Db::getInstance()->executeS($sql);
    }

    public function hookModuleRoutes()
    {
        require_once 'vendor/autoload.php'; // And the autoload here to make our Composer classes available everywhere!
    }

    private function initCache()
    {
        require_once dirname(__FILE__) . '/vendor/autoload.php'; // Autoload here for the module definition
        $filesystemAdapter = new \League\Flysystem\Adapter\Local(_PS_CACHE_DIR_ . 'cachefs');
        $filesystem = new \League\Flysystem\Filesystem($filesystemAdapter);

        $pool = new \Cache\Adapter\Filesystem\FilesystemCachePool($filesystem);
        $pool->setFolder($this->name);

        $this->cache = $pool;
    }
}
