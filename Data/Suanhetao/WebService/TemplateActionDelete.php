<?php 
$vars = get_defined_vars();
$modelName = $vars['modelName'];
$attributes = $vars['attributes'];
$modelInstanceName = lcfirst($modelName);
?>
namespace X\Module\Api\Action\<?php echo $modelName; ?>;
use X\Library\Suanhetao\WebServiceModule\ActionBase;
use X\Module\Api\Model\<?php echo $modelName; ?>;
class Delete extends ActionBase {
    /**
     * {@inheritDoc}
     * @see \X\Module\Api\Util\ActionBase::handle()
     */
    protected function handle( $id ) {
        $<?php echo $modelInstanceName; ?> = <?php echo $modelName; ?>::model()->findById($id);
        $this->assertOrThrow404(null === $<?php echo $modelInstanceName; ?>);
        
        $<?php echo $modelInstanceName; ?>->delete();
        $this->success();
    }
}