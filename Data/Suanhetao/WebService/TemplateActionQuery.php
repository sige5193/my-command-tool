<?php 
$vars = get_defined_vars();
$modelName = $vars['modelName'];
$attributes = $vars['attributes'];
$modelInstanceName = lcfirst($modelName);
?>
namespace X\Module\Api\Action\<?php echo $modelName; ?>;
use X\Library\Suanhetao\WebServiceModule\ActionQueryBase;
use X\Module\Api\Model\<?php echo $modelName; ?>;
class Query extends ActionQueryBase {
    /**
     * {@inheritDoc}
     * @see \X\Library\Suanhetao\WebServiceModule\ActionQueryBase::getResponseData()
     */
    protected function getResponseData() {
        return <?php echo $modelName; ?>::model()->findAll($this->getCriteria());
    }

    /**
     * {@inheritDoc}
     * @see \X\Library\Suanhetao\WebServiceModule\ActionQueryBase::getResponseTotalCount()
     */
    protected function getResponseTotalCount() {
        return <?php echo $modelName; ?>::model()->count($this->getCriteria()->condition);
    }
}