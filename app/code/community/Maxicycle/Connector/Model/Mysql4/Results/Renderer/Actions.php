<?php

class Aglumbik_Creditmemoext_Block_Adminhtml_Sales_Creditmemo_Renderer_Actions extends Mage_Adminhtml_Block_Widget_Grid_Column_Renderer_Abstract {

    public function render(Varien_Object $row) {
        // Load creditmemo for prefill values
        $creditmemo_info = Mage::getModel('sales/order_creditmemo')->load($row->getData('entity_id'));

        // Create for for custom actions
        $output = '<form method="post" action="' . Mage::getUrl('creditmemoext/adminhtml_creditmemoext/update') . '" id="form' . $row->getData('entity_id') . '"><input type="hidden" name="entity_id" value="' . $row->getData('entity_id') . '" /><input type="hidden" name="form_key" value="' . Mage::getSingleton('core/session')->getFormKey() . '" /><table>';

        // Custom status
        $custom_status = '<select name="custom_status" style="width:100%;">';
        foreach (Mage::helper('creditmemoext')->getCustomStatuses(false) as $value => $label) {
            $custom_status .= '<option' . (($value == $creditmemo_info->getCustomStatus()) ? ' selected="selected"' : '') . ' value="' . $value . '">' . $label . '</option>';
        }
        $custom_status .= '</select>';
        $output .= '<tr><td class="label">Stav:</td><td class="value">' . $custom_status . '</td></tr>';

        // Account number
        $output .= '<tr><td class="label">Číslo účtu:</td><td class="value"><input type="text" name="account_number" value="' . $creditmemo_info->getAccountNumber() . '" class="input-text" style="width:97%;" /></td></tr>';
        // New order ID
        $output .= '<tr><td class="label">ID nové objednávky:</td><td class="value"><input type="text" name="new_order_id" value="' . $creditmemo_info->getNewOrderId() . '" class="input-text" style="width:97%;" /></td></tr>';
        // Export date
        $output .= '<tr><td class="label">Datum exportu:</td><td class="value">&nbsp;</td></tr>';
        // Payment ID
        $output .= '<tr><td class="label">ID platby:</td><td class="value">&nbsp;</td></tr>';
        // Payment date
        $output .= '<tr><td class="label">Datum platby:</td><td class="value">&nbsp;</td></tr>';


        // End of form and save button
        $output .= '<tr><td>&nbsp;</td><td><button onclick="$(\'form' . $row->getData('entity_id') . '\').submit();" class="scalable task" type="button" title="Uložit údaje"><span><span><span>Uložit údaje</span></span></span></button></td></tr></table></form>';

        return $output;
    }

}
