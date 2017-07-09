<?php 
$vars = get_defined_vars();
$tableName = $vars['tableName'];
$modelName = $vars['modelName'];
$attributes = $vars['attributes'];
?>
namespace X\Module\Api\Model;
use X\Library\Suanhetao\WebServiceModule\ModelBase;
/**
<?php foreach ( $attributes as $attribute ) : ?>
 * @property string $<?php echo $attribute['name']; ?> <?php echo $attribute['comment'], "\n"; ?>
<?php endforeach; ?>
 */
class <?php echo $modelName; ?> extends ModelBase {
    /**
     * (non-PHPdoc)
     * @see \X\Service\XDatabase\Core\ActiveRecord\XActiveRecord::describe()
     */
    protected function describe() {
        return array_merge(parent::describe(), array(
        <?php foreach ( $attributes as $attribute ) : ?>
    '<?php echo $attribute['name']; ?>' => '<?php echo $attribute['description'];?>',
        <?php endforeach; ?>));
    }
    
    /**
     * (non-PHPdoc)
     * @see \X\Service\XDatabase\Core\ActiveRecord\XActiveRecord::getTableName()
     */
    protected function getTableName() {
        return '<?php echo $tableName; ?>';
    }
}