<?php 
$vars = get_defined_vars();
$modelName = $vars['modelName'];
$attributes = $vars['attributes'];
$modelInstanceName = lcfirst($modelName);
?>
namespace X\Module\Api\Action\<?php echo $modelName; ?>;
use X\Library\Suanhetao\WebServiceModule\ActionBase;
use X\Module\Api\Model\<?php echo $modelName; ?>;
class TemplateActionReview extends ActionBase {
    /**
     * {@inheritDoc}
     * @see \X\Module\Api\Util\ActionBase::handle()
     */
    protected function handle( $id, $status, $message ) {
        $<?php echo $modelInstanceName; ?> = <?php echo $modelName; ?>::model()->findById($id);
        $this->assertOrThrow404(null === $<?php echo $modelInstanceName; ?>);
        
        if ( <?php echo $modelName; ?>::STATUS_REVIEWED == $status ) {
            $<?php echo $modelInstanceName; ?>->record_visibility = <?php echo $modelName; ?>::VISIBILITY_PUBLIC;
        } else if ( <?php echo $modelName; ?>::STATUS_REVIEWED_FAILED == $status ) {
            $<?php echo $modelInstanceName; ?>->record_visibility = <?php echo $modelName; ?>::VISIBILITY_PRIVATE;
        } else {
            return $this->error("invalid status", self::ERROR_CODE_ERR_PARAM);
        }
        
        $<?php echo $modelInstanceName; ?>->record_status = $status;
        $<?php echo $modelInstanceName; ?>->record_comment = $message;
        $<?php echo $modelInstanceName; ?>->save();
        $this->success();
    }
}