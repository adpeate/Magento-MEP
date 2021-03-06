<?php
/**
 * Helper
 *
 * @category Flagbit_MEP
 * @package Flagbit_MEP
 * @author Damian Luszczymak <damian.luszczymak@flagbit.de>
 * @copyright 2012 Flagbit GmbH & Co. KG (http://www.flagbit.de). All rights served.
 * @license http://opensource.org/licenses/gpl-3.0 GNU General Public License, version 3 (GPLv3)
 * @version 0.1.0
 * @since 0.1.0
 */

class Flagbit_MEP_Helper_Data extends Mage_Core_Helper_Abstract
{
    const CONFIG_KEY_FORMATS  = 'global/mep/export_file_formats';
    /**
     * get current Profile Data
     *
     * @param bool $idOnly
     * @return array|null|string
     */
    public function getCurrentProfileData($idOnly = false)
    {
        if (Mage::getSingleton('adminhtml/session')->getMepProfileData()) {
            $data = Mage::getSingleton('adminhtml/session')->getMepProfileData();
        } elseif (Mage::registry('mep_profile_data')) {
            $data = Mage::registry('mep_profile_data')->getData();
        } else {
            $data = array();
        }
        if(is_bool($idOnly) && $idOnly === true){
            $data = isset($data['id']) ? $data['id'] : null;
        }elseif($idOnly){
            $data = isset($data[$idOnly]) ? $data[$idOnly] : '';
        }
        else {
            if (empty($data['ftp_host_port'])) {
                $data['ftp_host_port'] = ':21';
            }
            if (empty($data['ftp_path'])) {
                $data['ftp_path'] = '/';
            }
        }
        return $data;
    }

    /**
     * normalize strings (to use it as a variable name)
     *
     * @param array|string $mixed
     * @return mixed|null
     */
    public function normalizeVariableName($mixed)
    {
        $result = null;
        if (is_array($mixed)) {
            foreach ($mixed as &$value) {
                $value = $this->normalizeVariableName($value);
            }
            $result = $mixed;
        } else {
            $mixed = $this->normalize($mixed);
            $string = str_replace(array(' ', '-'), '_', $mixed);
            $result = preg_replace('([^A-Za-z0-9_]*)', '', $mixed);
        }
        return $result;
    }


    /**
     * normalize Characters
     * Example: ü -> ue
     *
     * @param string $string
     * @return string
     */
    public function normalize($string)
    {
        $table = array(
            'Š' => 'S', 'š' => 's', 'Đ' => 'Dj', 'đ' => 'dj', 'Ž' => 'Z', 'ž' => 'z', 'Č' => 'C', 'č' => 'c', 'Ć' => 'C', 'ć' => 'c',
            'À' => 'A', 'Á' => 'A', 'Â' => 'A', 'Ã' => 'A', 'Ä' => 'Ae', 'Å' => 'A', 'Æ' => 'A', 'Ç' => 'C', 'È' => 'E', 'É' => 'E',
            'Ê' => 'E', 'Ë' => 'E', 'Ì' => 'I', 'Í' => 'I', 'Î' => 'I', 'Ï' => 'I', 'Ñ' => 'N', 'Ò' => 'O', 'Ó' => 'O', 'Ô' => 'O',
            'Õ' => 'O', 'Ö' => 'Oe', 'Ø' => 'O', 'Ù' => 'U', 'Ú' => 'U', 'Û' => 'U', 'Ü' => 'Ue', 'Ý' => 'Y', 'Þ' => 'B', 'ß' => 'Ss',
            'à' => 'a', 'á' => 'a', 'â' => 'a', 'ã' => 'a', 'ä' => 'ae', 'å' => 'a', 'æ' => 'a', 'ç' => 'c', 'è' => 'e', 'é' => 'e',
            'ê' => 'e', 'ë' => 'e', 'ì' => 'i', 'í' => 'i', 'î' => 'i', 'ï' => 'i', 'ð' => 'o', 'ñ' => 'n', 'ò' => 'o', 'ó' => 'o',
            'ô' => 'o', 'õ' => 'o', 'ö' => 'oe', 'ø' => 'o', 'ù' => 'u', 'ú' => 'u', 'û' => 'u', 'ý' => 'y', 'ý' => 'y', 'þ' => 'b',
            'ÿ' => 'y', 'Ŕ' => 'R', 'ŕ' => 'r', 'ü' => 'ue',
        );

        return strtr($string, $table);
    }

    public function getProductsCollection()
    {
        $collection = Mage::getModel('catalog/product')->getCollection()
            ->addAttributeToSelect('sku')
            ->addAttributeToSelect('name')
            ->addAttributeToSelect('attribute_set_id')
            ->addAttributeToSelect('type_id');

        if (Mage::helper('catalog')->isModuleEnabled('Mage_CatalogInventory')) {
            $collection->getSelect()->joinLeft(
                array('_inventory_table' => $collection->getTable('cataloginventory/stock_item')),
                '_inventory_table.product_id = e.entity_id',
                array('qty', 'is_in_stock', 'manage_stock')
            );
        }
        return $collection;
    }

    public function getNewWriteInstance($destinationFile, $fileFormat)
    {
        $validWriters = Mage_ImportExport_Model_Config::getModels(self::CONFIG_KEY_FORMATS);

        if (isset($validWriters[$fileFormat])) {
            try {
                if(file_exists($destinationFile) && strpos($destinationFile, 'tmp')){
                    unlink($destinationFile);
                }
                $writer = Mage::getModel($validWriters[$fileFormat]['model'], $destinationFile);
            } catch (Exception $e) {
                Mage::logException($e);
                Mage::throwException(
                    Mage::helper('importexport')->__('Invalid entity model')
                );
            }
            if (! $writer instanceof Mage_ImportExport_Model_Export_Adapter_Abstract) {
                Mage::throwException(
                    Mage::helper('importexport')->__('Adapter object must be an instance of %s', 'Mage_ImportExport_Model_Export_Adapter_Abstract')
                );
            }
        } else {
            Mage::throwException(Mage::helper('importexport')->__('Invalid file format'));
        }
        return $writer;
    }
}

