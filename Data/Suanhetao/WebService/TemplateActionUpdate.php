<?php 
$vars = get_defined_vars();
$modelName = $vars['modelName'];
$attributes = $vars['attributes'];
$modelInstanceName = lcfirst($modelName);
?>
namespace X\Module\Api\Action\<?php echo $modelName; ?>;
use X\Library\Suanhetao\WebServiceModule\ActionUpdateBase;
use X\Module\Api\Model\<?php echo $modelName; ?>;
class Update extends ActionUpdateBase {
    /**
     * {@inheritDoc}
     * @see \X\Module\Api\Util\ActionUpdateBase::getUpdateRules()
     */
    protected function getUpdateRules($data) {
        return array();
    }
    
    /**
     * {@inheritDoc}
     * @see \X\Module\Api\Util\ActionUpdateBase::getUpdateAttrWhiteList()
     */
    protected function getUpdateAttrWhiteList() {
        return array(
            <?php foreach ( $attributes as $index => $name ) : 
            ?><?php if ( 0!==$index && 0===$index%4 ) {echo "\n            ";} 
            ?>'<?php echo $name; ?>',<?php endforeach; ?>
            
        );
    }
    
    /**
     * {@inheritDoc}
     * @see \X\Module\Api\Util\ActionBase::handle()
     */
    protected function handle( $id, $data ) {
        $<?php echo $modelInstanceName; ?> = <?php echo $modelName; ?>::model()->findById($id);
        if ( null === $<?php echo $modelInstanceName; ?> ) {
            return $this->error404();
        }
        $<?php echo $modelInstanceName; ?>->setAttributeValues($data);
        $<?php echo $modelInstanceName; ?>->save();
    }
}