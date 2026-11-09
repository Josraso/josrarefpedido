<?php
/**
 * 1.1.0: avisos de actualización (servidor de actualizaciones de josra y,
 * en PrestaShop 8.1 o superior, pestaña nativa "Actualizaciones").
 *
 * @author josra
 */
if (!defined('_PS_VERSION_')) {
    exit;
}

function upgrade_module_1_1_0($module)
{
    return $module->getUpdater()->registerHooks();
}
