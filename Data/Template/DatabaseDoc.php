<?php 
$vars = get_defined_vars();
$tables = $vars['tables'];

$style = array();
$style['table'] = 'width:100%;border-collapse: collapse;';
$style['tableHeader'] = 'border: 1px solid #030303;background-color: #ccc2c2;font-weight: bold;text-align: center;';
$style['tableCell'] = 'border: 1px solid #030303;text-align: center;';
?>
<h1>数据表清单</h1>
<table style="<?php echo $style['table']; ?>">
  <thead>
    <tr>
       <td style="<?php echo $style['tableHeader']; ?>">序号</td>
       <td style="<?php echo $style['tableHeader']; ?>">表名</td>
       <td style="<?php echo $style['tableHeader']; ?>">中文名称</td>
       <td style="<?php echo $style['tableHeader']; ?>">主键</td>
       <td style="<?php echo $style['tableHeader']; ?>">最大记录数</td>
       <td style="<?php echo $style['tableHeader']; ?>">更新频度</td>
       <td style="<?php echo $style['tableHeader']; ?>">备注</td>
     </tr>
  </thead>
  <tbody>
  <?php foreach ( $tables as $index => $table ) : ?>
  <tr>
    <td style="<?php echo $style['tableCell']; ?>"><?php echo $index + 1; ?></td>
    <td style="<?php echo $style['tableCell']; ?>"><?php echo $table['name']; ?></td>
    <td style="<?php echo $style['tableCell']; ?>"><?php echo $table['chinese']; ?></td>
    <td style="<?php echo $style['tableCell']; ?>"><?php echo $table['primaryKey']; ?></td>
    <td style="<?php echo $style['tableCell']; ?>"></td>
    <td style="<?php echo $style['tableCell']; ?>"></td>
    <td style="<?php echo $style['tableCell']; ?>"></td>
  </tr>
  <?php endforeach; ?>
  </tbody>
</table>
<h1>数据表结构</h1>
<?php foreach ( $tables as $index => $table ) : ?>
<h2><?php echo $table['chinese']; ?>(<?php echo $table['name']; ?>)</h2>
<table style="<?php echo $style['table']; ?>">
  <thead>
    <tr>
       <td rowspan="2" style="<?php echo $style['tableHeader']; ?>">序号</td>
       <td colspan="2" style="<?php echo $style['tableHeader']; ?>">列名称</td>
       <td rowspan="2" style="<?php echo $style['tableHeader']; ?>">主键</td>
       <td rowspan="2" style="<?php echo $style['tableHeader']; ?>">类型</td>
       <td rowspan="2" style="<?php echo $style['tableHeader']; ?>">长度</td>
       <td rowspan="2" style="<?php echo $style['tableHeader']; ?>">单位</td>
       <td rowspan="2" style="<?php echo $style['tableHeader']; ?>">NOT<br>NULL</td>
       <td rowspan="2" style="<?php echo $style['tableHeader']; ?>">缺<br>省<br>值</td>
       <td colspan="2" style="<?php echo $style['tableHeader']; ?>">取值范围</td>
       <td rowspan="2" style="<?php echo $style['tableHeader']; ?>">备注</td>
     </tr>
     <tr>
       <td style="<?php echo $style['tableHeader']; ?>">英文名称</td>
       <td style="<?php echo $style['tableHeader']; ?>">中文名称</td>
       <td style="<?php echo $style['tableHeader']; ?>">下限</td>
       <td style="<?php echo $style['tableHeader']; ?>">上限</td>
     </tr>
  </thead>
  <tbody>
  <?php foreach ( $table['columns'] as $colIndex => $column ) : ?>
  <tr>
    <td style="<?php echo $style['tableCell']; ?>"><?php echo $colIndex + 1; ?></td>
    <td style="<?php echo $style['tableCell']; ?>"><?php echo $column['name']; ?></td>
    <td style="<?php echo $style['tableCell']; ?>"><?php echo $column['chinese']; ?></td>
    <td style="<?php echo $style['tableCell']; ?>"><?php echo $column['isPrimaryKey']; ?></td>
    <td style="<?php echo $style['tableCell']; ?>"><?php echo $column['type']; ?></td>
    <td style="<?php echo $style['tableCell']; ?>"><?php echo $column['length']; ?></td>
    <td style="<?php echo $style['tableCell']; ?>"></td>
    <td style="<?php echo $style['tableCell']; ?>"><?php echo $column['notNull']; ?></td>
    <td style="<?php echo $style['tableCell']; ?>"></td>
    <td style="<?php echo $style['tableCell']; ?>"></td>
    <td style="<?php echo $style['tableCell']; ?>"></td>
    <td style="<?php echo $style['tableCell']; ?>"></td>
  </tr>
  <?php endforeach; ?>
  </tbody>
</table>
<?php endforeach; ?>