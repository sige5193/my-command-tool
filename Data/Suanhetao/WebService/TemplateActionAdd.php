<?php 
$vars = get_defined_vars();
$modelName = $vars['modelName'];
$attributes = $vars['attributes'];
$modelInstanceName = lcfirst($modelName);
?>
namespace X\Module\Api\Action\<?php echo $modelName; ?>;
use X\Library\Suanhetao\WebServiceModule\ActionAddBase;
use X\Module\Api\Model\<?php echo $modelName; ?>;
class Add extends ActionAddBase {
    /**
     * {@inheritDoc}
     * @see \X\Module\Api\Util\ActionAddBase::getAddRules()
     */
    protected function getAddRules($data) {
        return array();
    }
    
    /**
     * {@inheritDoc}
     * @see \X\Module\Api\Util\ActionAddBase::getAddtionAttrWhiteList()
     */
    protected function getAddtionAttrWhiteList() {
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
    protected function handle($data) {
        $<?php echo $modelInstanceName; ?> = new <?php echo $modelName; ?>();
        $<?php echo $modelInstanceName; ?>->setAttributeValues($data);
        $<?php echo $modelInstanceName; ?>->save();
        
        $this->setSavedData($<?php echo $modelInstanceName; ?>);
    }
}