<?php
use PrestaShop\PrestaShop\Core\Module\WidgetInterface;

if (!defined('_PS_VERSION_')) {
    exit;
}

class PersonalMessage extends Module implements WidgetInterface
{

    //Array que contiene los templates que usaremos para las 2 funcionalidades
    private $templates =array (
        'welcome' => 'personalmessage.tpl',
        'contact' => 'modal_contactform.tpl'
    );

    public function __construct()
    {
        $this->name = 'personalmessage';
        $this->version = '0.0.1';
        $this->author = 'Yoshua Lino';
        $this->ps_versions_compliancy = array('min' => '1.7', 'max' => _PS_VERSION_);
        $this->bootstrap = true;
        $this->need_instance = 0;

        parent::__construct();

        $this->displayName = $this->trans('Mensaje Personalizado Yoshua', array(), 'Modules.PersonalMessage.Admin');
        $this->displayName = $this->trans('Mensaje personalizado y mensaje atención al cliente', array(), 'Modules.PersonalMessage.Admin');

    }

    public function install()
    {
        if (parent::install() && Configuration::updateValue('PERSONAL_WELCOME', '') && Configuration::updateValue('PERSONAL_WELCOME_VISIBLE', 0) 
        && $this->registerHook(['displayNavFullWidth','displayProductActions','actionFrontControllerSetMedia'])) {
            $this->emptyTemplatesCache();
            return true;
        } else {
            return false;
        }
    }

    public function uninstall()
    {
        $this->_clearCache('*');
        if (!parent::uninstall()
        ) {
            return false;
        } else {
            return Configuration::deleteByName('PERSONAL_WELCOME') && Configuration::deleteByName('PERSONAL_WELCOME_VISIBLE');
        }
    }

    //Funcionalidad para actualizar info en bbdd y cargar formulario en el backend del módulo
    public function getContent()
    {
        $output = null;
    
        if (Tools::isSubmit('submitModule')) {
            $personal_welcome = Tools::getValue('personal_welcome');
            $personal_welcome_visible = Tools::getValue('personal_welcome_visible');
            if (
                (!$personal_welcome ||
                empty($personal_welcome)) &&
                $personal_welcome_visible
            ) {
                $output .= $this->displayError($this->l('Valores incorrectos'));
            } else {
                 Configuration::updateValue('PERSONAL_WELCOME', $personal_welcome,true);
                Configuration::updateValue('PERSONAL_WELCOME_VISIBLE', $personal_welcome_visible);
                $output .= $this->displayConfirmation($this->l('Configuración actualizada'));
            }
        }
        
        return $output.$this->renderForm();
    }

    //generamos formulario de personalización backend
    public function renderForm()
    {
        $fields_form = array(
            'form' => array(
                'tinymce' => true,
                'legend' => array(
                    'title' => $this->trans('Settings', array(), 'Admin.Global'),
                    'icon' => 'icon-cogs',
                ),
                'input' => array(
                    array(
                        'type' => 'textarea',
                        'label' => $this->trans('Welcome Message', array(), 'Modules.PersonalMessage.Admin'),
                        'name' => 'personal_welcome',
                        'autoload_rte' => true,
                        'desc' => $this->trans('Your Welcome Message In Front', array(), 'Modules.PersonalMessage.Admin'),
                    ),
                    array(
                        'type' => 'switch',
                        'label' => $this->trans('Welcome Message Active', array(), 'Modules.PersonalMessage.Admin'),
                        'name' => 'personal_welcome_visible',
                        'desc' => $this->trans('Your Welcome Message Visible In Front', array(), 'Modules.PersonalMessage.Admin'),
                        'values' => array(
                            array(
                                'id' => 'active_on',
                                'value' => 1,
                                'label' => $this->trans('Yes', array(), 'Admin.Global')
                            ),
                            array(
                                'id' => 'active_off',
                                'value' => 0,
                                'label' => $this->trans('No', array(), 'Admin.Global')
                            )
                        )
                    )
                ),
                'submit' => array(
                    'title' => $this->trans('Save', array(), 'Admin.Global'),
                ),
            ),
        );

        $helper = new HelperForm();
        $helper->show_toolbar = false;
        $helper->table = $this->table;
        $helper->submit_action = 'submitModule';
        $helper->tpl_vars = array(
            'fields_value' => $this->getConfigFieldsValues(),
        );

        return $helper->generateForm(array($fields_form));
    }

    //recogemos de bbdd los datos del formulario de backend
    public function getConfigFieldsValues()
    {
        return array(
            'personal_welcome' => Tools::getValue('personal_welcome', Configuration::get('PERSONAL_WELCOME')),
            'personal_welcome_visible' => Tools::getValue('personal_welcome_visible', Configuration::get('PERSONAL_WELCOME_VISIBLE')),

        );
    }

    //Carga el contenido la tpl del módulo en el hook o hooks al que lo anclemos
    public function renderWidget($hookName = null, array $configuration = [])
    {

        if ($hookName == null && isset($configuration['hook'])) {
            $hookName = $configuration['hook'];
        }

        if ($hookName == 'displayNavFullWidth') {
            $template_file = 'module:personalmessage/views/templates/hook/'.$this->templates['welcome'];
        } elseif ($hookName == 'displayProductActions') {
            $template_file = 'module:personalmessage/views/templates/hook/'.$this->templates['contact'];
        }

        if (!$this->isCached($template_file, $this->getCacheId('personalmessage'))) {
            $this->smarty->assign($this->getWidgetVariables($hookName, $configuration));
        }

        return $this->fetch($template_file, $this->getCacheId('personalmessage'));

    }

    //cargamos las variables utilizadas en la tpl
    public function getWidgetVariables($hookName = null, array $configuration = [])
    {
        if (Configuration::get('PERSONAL_WELCOME_VISIBLE')) {
            $personal_welcome = Configuration::get('PERSONAL_WELCOME');
        }else{
            $personal_welcome = '';
        }

        $logged = $this->context->customer->isLogged();

        if ($logged) {
            $customerName = $this->getTranslator()->trans(
                '%firstname% %lastname%',
                [
                    '%firstname%' => $this->context->customer->firstname,
                    '%lastname%' => $this->context->customer->lastname,
                ],
                'Modules.personalmessage.Admin'
            );
        } else {
            $customerName = 'Invitado';
        }

        //info del usuario y mensaje personalizado
        return array(
            'personal_welcome_html' => htmlspecialchars_decode($personal_welcome),
            'customer_name' => htmlspecialchars_decode($customerName),
        );
    }

    //borrar la cache de la tpl utilizadas en el modulo
    private function emptyTemplatesCache()
    {
        $this->_clearCache('personalmessage.tpl');
    }

    //Cargaremos los ficheros de estilos y javascript necesarios
    public function hookActionFrontControllerSetMedia($params)
    {
        // Solo lo carga en la página de producto
        if ('product' === $this->context->controller->php_self) {
            $this->context->controller->registerStylesheet(
                'module-personalmessage-modalcontact-style',
                'modules/'.$this->name.'/views/templates/css/modal_contact.css',
                [
                  'media' => 'all',
                  'priority' => 200,
                ]
            );
            $this->context->controller->registerStylesheet(
                'jquery-fancybox-style',
                'js/jquery/plugins/fancybox/jquery.fancybox.css',
                [
                  'media' => 'all',
                  'priority' => 200,
                ]
            );
            $this->context->controller->registerJavascript(
                'module-personalmessage-modalcontact-js',
                'modules/'.$this->name.'/views/templates/js/modal_contact.js',
                [
                  'priority' => 200,
                  'attribute' => 'async',
                ]
            );
            $this->context->controller->registerJavascript(
                'jquery-fancybox-js',
                'js/jquery/plugins/fancybox/jquery.fancybox.js',
                [
                  'priority' => 200,
                  'attribute' => 'async',
                ]
            );
        }
    }    

}
