<?php
/**
 * Referencia de pedido = ID
 *
 * Sustituye la referencia aleatoria de 9 letras que PrestaShop asigna a cada
 * pedido por el número de pedido (id_order).
 *
 * Compatible con PrestaShop 1.7.x, 8.x y 9.x
 */
if (!defined('_PS_VERSION_')) {
    exit;
}

require_once dirname(__FILE__) . '/classes/JosrarefpedidoUpdater.php';

class Josrarefpedido extends Module
{
    const CFG_ENABLED = 'JOSRAREFPEDIDO_ENABLED';

    /** Columna orders.reference es VARCHAR(9) */
    const MAX_LEN = 9;

    /**
     * Mapa referencia aleatoria => referencia numérica dentro de la misma
     * petición. Necesario cuando un carrito se divide en varios pedidos
     * (varios transportistas): todos comparten referencia y deben seguir
     * compartiéndola tras el cambio.
     *
     * @var array<string,string>
     */
    private static $map = [];

    public function __construct()
    {
        $this->name = 'josrarefpedido';
        $this->tab = 'administration';
        $this->version = '1.1.2';
        $this->author = 'Jose';
        $this->need_instance = 0;
        $this->bootstrap = true;
        $this->ps_versions_compliancy = ['min' => '1.7.0.0', 'max' => _PS_VERSION_];

        parent::__construct();

        $this->displayName = $this->l('Referencia de pedido = ID');
        $this->description = $this->l('Sustituye la referencia aleatoria de letras de los pedidos por el número de pedido (ID).');
        $this->confirmUninstall = $this->l('Los pedidos ya renumerados conservarán su referencia numérica. ¿Desinstalar?');
    }

    public function install()
    {
        return parent::install()
            && $this->registerHook('actionValidateOrder')
            && $this->getUpdater()->registerHooks()
            && Configuration::updateValue(self::CFG_ENABLED, 1);
    }

    public function uninstall()
    {
        // Aviso al servidor de actualizaciones (no impide desinstalar si no responde)
        $this->getUpdater()->notifyUninstall();

        Configuration::deleteByName('JOSRAREFPEDIDO_UPD_TS');
        Configuration::deleteByName('JOSRAREFPEDIDO_UPD_INFO');

        return Configuration::deleteByName(self::CFG_ENABLED)
            && parent::uninstall();
    }

    /**
     * Se ejecuta dentro de PaymentModule::validateOrder(), con el pedido ya
     * guardado (tiene id) y ANTES de enviar el email de confirmación, así que
     * el cliente ya recibe el número correcto.
     */
    public function hookActionValidateOrder($params)
    {
        if (!Configuration::get(self::CFG_ENABLED)) {
            return;
        }

        $order = isset($params['order']) ? $params['order'] : null;
        if (!Validate::isLoadedObject($order)) {
            return;
        }

        $old = (string) $order->reference;
        if ($old === '') {
            return;
        }

        if (isset(self::$map[$old])) {
            // Segundo/tercer pedido del mismo carrito dividido: la BD ya está
            // actualizada, solo hay que sincronizar el objeto en memoria.
            $new = self::$map[$old];
        } else {
            if (ctype_digit($old)) {
                return; // Ya es numérica
            }

            $new = (string) (int) $order->id;
            if (Tools::strlen($new) > self::MAX_LEN) {
                return;
            }

            if (!$this->replaceReference($old, $new)) {
                PrestaShopLogger::addLog(
                    'josrarefpedido: no se pudo cambiar la referencia ' . $old . ' a ' . $new,
                    3,
                    null,
                    'Order',
                    (int) $order->id,
                    true
                );

                return;
            }

            self::$map[$old] = $new;
        }

        // Objeto que PaymentModule sigue usando (emails, etc.)
        $order->reference = $new;

        // Referencia expuesta por el módulo de pago que está validando el pedido
        $paymentModule = Module::getInstanceByName($order->module);
        if ($paymentModule
            && property_exists($paymentModule, 'currentOrderReference')
            && $paymentModule->currentOrderReference === $old
        ) {
            $paymentModule->currentOrderReference = $new;
        }
    }

    /**
     * Cambia una referencia en pedidos y pagos.
     */
    private function replaceReference($old, $new)
    {
        $db = Db::getInstance();
        $where = '`reference` = \'' . pSQL($old) . '\'';
        $wherePayment = '`order_reference` = \'' . pSQL($old) . '\'';

        return $db->update('orders', ['reference' => pSQL($new)], $where)
            && $db->update('order_payment', ['order_reference' => pSQL($new)], $wherePayment);
    }

    /**
     * Convierte los pedidos existentes. Cada grupo de pedidos que comparte
     * referencia toma el ID más bajo del grupo.
     *
     * @return int número de referencias convertidas
     */
    private function convertExistingOrders()
    {
        $rows = Db::getInstance()->executeS(
            'SELECT `reference`, MIN(`id_order`) AS `id_order`
             FROM `' . _DB_PREFIX_ . 'orders`
             WHERE `reference` <> \'\' AND `reference` NOT REGEXP \'^[0-9]+$\'
             GROUP BY `reference`'
        );

        $count = 0;
        if (!$rows) {
            return 0;
        }

        foreach ($rows as $row) {
            $new = (string) (int) $row['id_order'];
            if (Tools::strlen($new) > self::MAX_LEN) {
                continue;
            }
            if ($this->replaceReference($row['reference'], $new)) {
                ++$count;
            }
        }

        return $count;
    }

    private function countPendingOrders()
    {
        return (int) Db::getInstance()->getValue(
            'SELECT COUNT(DISTINCT `reference`) FROM `' . _DB_PREFIX_ . 'orders`
             WHERE `reference` <> \'\' AND `reference` NOT REGEXP \'^[0-9]+$\''
        );
    }

    /* ------------------------------------------------------------------ */
    /* Actualizaciones                                                     */
    /* ------------------------------------------------------------------ */

    public function getUpdater()
    {
        return new JosrarefpedidoUpdater($this);
    }

    /**
     * Aviso diario al servidor de actualizaciones (solo en el back-office).
     */
    public function hookActionAdminControllerSetMedia($params)
    {
        $this->getUpdater()->maybePing();
    }

    /**
     * PS 8.1+: la versión nueva aparece en Módulos > Gestor de módulos > Actualizaciones.
     */
    public function hookActionListModules($params)
    {
        return $this->getUpdater()->getNativeModuleList();
    }

    public function getContent()
    {
        $updater = $this->getUpdater();
        $output = $updater->processRequest();

        if (Tools::isSubmit('submitJosrarefpedidoConfig')) {
            Configuration::updateValue(self::CFG_ENABLED, (int) Tools::getValue(self::CFG_ENABLED));
            $output .= $this->displayConfirmation($this->l('Configuración guardada.'));
        }

        if (Tools::isSubmit('submitJosrarefpedidoConvert')) {
            $n = $this->convertExistingOrders();
            $output .= $this->displayConfirmation(
                sprintf($this->l('Referencias convertidas: %d'), $n)
            );
        }

        $info = $updater->getInfo();
        $panel = $updater->renderPanel();

        return !empty($info['update_available'])
            ? $output . $panel . $this->renderForm()
            : $output . $this->renderForm() . $panel;
    }

    private function renderForm()
    {
        $pending = $this->countPendingOrders();

        $forms = [];

        $forms[] = [
            'form' => [
                'legend' => [
                    'title' => $this->l('Configuración'),
                    'icon' => 'icon-cogs',
                ],
                'input' => [
                    [
                        'type' => 'switch',
                        'label' => $this->l('Usar el ID como referencia'),
                        'name' => self::CFG_ENABLED,
                        'is_bool' => true,
                        'desc' => $this->l('Los pedidos nuevos tendrán como referencia su número de pedido (ej. 125) en lugar de letras aleatorias (ej. XKBWQLRTA).'),
                        'values' => [
                            ['id' => 'active_on', 'value' => 1, 'label' => $this->l('Sí')],
                            ['id' => 'active_off', 'value' => 0, 'label' => $this->l('No')],
                        ],
                    ],
                ],
                'submit' => [
                    'title' => $this->l('Guardar'),
                    'name' => 'submitJosrarefpedidoConfig',
                ],
            ],
        ];

        $forms[] = [
            'form' => [
                'legend' => [
                    'title' => $this->l('Pedidos existentes'),
                    'icon' => 'icon-refresh',
                ],
                'input' => [
                    [
                        'type' => 'html',
                        'name' => 'josrarefpedido_info',
                        'html_content' => '<div class="alert ' . ($pending > 0 ? 'alert-warning' : 'alert-success') . '">'
                            . ($pending > 0
                                ? sprintf($this->l('Hay %d referencias con letras. Puedes convertirlas al número de pedido. Haz copia de seguridad antes: el cambio no se puede deshacer.'), $pending)
                                : $this->l('Todos los pedidos tienen ya referencia numérica.'))
                            . '</div>',
                    ],
                ],
                'submit' => $pending > 0 ? [
                    'title' => $this->l('Convertir pedidos existentes'),
                    'name' => 'submitJosrarefpedidoConvert',
                    'icon' => 'process-icon-refresh',
                ] : [],
            ],
        ];

        $helper = new HelperForm();
        $helper->show_toolbar = false;
        $helper->table = $this->table;
        $helper->module = $this;
        $helper->default_form_language = (int) Configuration::get('PS_LANG_DEFAULT');
        $helper->allow_employee_form_lang = (int) Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG');
        $helper->identifier = $this->identifier;
        // Sin submit_action global: cada botón envía su propio nombre
        $helper->submit_action = '';
        $helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false)
            . '&configure=' . $this->name . '&tab_module=' . $this->tab . '&module_name=' . $this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->tpl_vars = [
            'fields_value' => [
                self::CFG_ENABLED => (int) Configuration::get(self::CFG_ENABLED),
            ],
            'languages' => $this->context->controller->getLanguages(),
            'id_language' => $this->context->language->id,
        ];

        return $helper->generateForm($forms);
    }
}
