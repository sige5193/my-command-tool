<?php 
$vars = get_defined_vars();
$funcs = $vars['funcs'];

$style = array();
$style['table'] = 'width:100%;border-collapse: collapse;';
$style['tableHeader'] = 'border: 1px solid #030303;background-color: #ccc2c2;font-weight: bold;text-align: center;';
$style['tableCell'] = 'border: 1px solid #030303;text-align: center;';

$dataMap = array(
    'type' => array('default'=>'文本', 'BigDecimal'=>'数值', 'Date'=>'日期'),
);
?>
<h1>画面设计逻辑</h1>
<h2>画面清单</h2>
<table style="<?php echo $style['table']; ?>">
  <thead>
    <tr>
       <td style="<?php echo $style['tableHeader']; ?>">序号</td>
       <td style="<?php echo $style['tableHeader']; ?>">画面号</td>
       <td style="<?php echo $style['tableHeader']; ?>">画面名称</td>
       <td style="<?php echo $style['tableHeader']; ?>">画面功能</td>
       <td style="<?php echo $style['tableHeader']; ?>">备注</td>
     </tr>
  </thead>
  <tbody>
  <?php foreach ( $funcs as $index => $func ) : ?>
  <tr>
    <td style="<?php echo $style['tableCell']; ?>"><?php echo $index + 1; ?></td>
    <td style="<?php echo $style['tableCell']; ?>">UI_ST_<?php echo sprintf('%03d', $index+1); ?></td>
    <td style="<?php echo $style['tableCell']; ?>"><?php echo $func['name']; ?></td>
    <td style="<?php echo $style['tableCell']; ?>"></td>
    <td style="<?php echo $style['tableCell']; ?>"></td>
  </tr>
  <?php endforeach; ?>
  </tbody>
</table>

<?php foreach ( $funcs as $index => $func ) : ?>
<h2><?php echo $func['name']; ?>(UI_ST_<?php echo sprintf('%03d', $index+1); ?>)</h2>
<h3>画面格式及数据项说明</h3>
<table style="<?php echo $style['table']; ?>">
  <thead>
    <tr>
       <td style="<?php echo $style['tableHeader']; ?>">中文字段名</td>
       <td style="<?php echo $style['tableHeader']; ?>">数据类型及长度</td>
       <td style="<?php echo $style['tableHeader']; ?>">必输项否</td>
       <td style="<?php echo $style['tableHeader']; ?>">默认值</td>
       <td style="<?php echo $style['tableHeader']; ?>">可写否</td>
       <td style="<?php echo $style['tableHeader']; ?>">显示否</td>
       <td style="<?php echo $style['tableHeader']; ?>">备注</td>
     </tr>
  </thead>
  <tbody>
  <?php foreach ( $func['labels'] as $label ) : ?>
  <tr>
    <td style="<?php echo $style['tableCell']; ?>"><?php echo $label['text']; ?></td>
    <td style="<?php echo $style['tableCell']; ?>">
      <?php echo isset($dataMap['type'][$label['type']]) ? $dataMap['type'][$label['type']] : $dataMap['type']['default']; ?>
    </td>
    <td style="<?php echo $style['tableCell']; ?>">
      <?php echo (null===$label['required']) ? 'N' : 'Y'; ?>
    </td>
    <td style="<?php echo $style['tableCell']; ?>"><?php echo $label['default']; ?></td>
    <td style="<?php echo $style['tableCell']; ?>">Y</td>
    <td style="<?php echo $style['tableCell']; ?>">Y</td>
    <td style="<?php echo $style['tableCell']; ?>"></td>
  </tr>
  <?php endforeach; ?>
  </tbody>
</table>

<h3>画面功能说明</h3>
<table style="<?php echo $style['table']; ?>">
  <thead>
    <tr>
       <td style="<?php echo $style['tableHeader']; ?>">功能名称</td>
       <td style="<?php echo $style['tableHeader']; ?>">功能代码</td>
       <td style="<?php echo $style['tableHeader']; ?>">操作序列</td>
     </tr>
  </thead>
  <tbody>
  <?php foreach ( $func['buttons'] as $button ) : ?>
  <tr>
    <td style="<?php echo $style['tableCell']; ?>"><?php echo $button['name']; ?></td>
    <td style="<?php echo $style['tableCell']; ?>"><?php echo $button['classMethod']; ?></td>
    <td style="<?php echo $style['tableCell']; ?>"><?php echo $button['description']; ?></td>
  </tr>
  <?php endforeach; ?>
  </tbody>
</table>

<h3>画面处理逻辑说明</h3>
<table style="<?php echo $style['table']; ?>">
  <thead>
    <tr>
       <td style="<?php echo $style['tableHeader']; ?>">服务名称</td>
       <td style="<?php echo $style['tableHeader']; ?>">程序代码</td>
       <td style="<?php echo $style['tableHeader']; ?>" colspan="2">IPO设计</td>
     </tr>
  </thead>
  <?php foreach ( $func['logic'] as $logic ) : ?>
  <tr>
    <td style="<?php echo $style['tableCell']; ?>" rowspan="3"><?php echo $logic['name']; ?></td>
    <td style="<?php echo $style['tableCell']; ?>" rowspan="3"><?php echo $logic['code']; ?></td>
    <td style="<?php echo $style['tableCell']; ?> font-weight: bold;">输<br>入</td>
    <td style="<?php echo $style['tableCell']; ?>"><?php echo implode("<br>", $logic['input']); ?></td>
  </tr>
  <tr>
    <td style="<?php echo $style['tableCell']; ?> font-weight: bold;">处<br>理</td>
    <td style="<?php echo $style['tableCell']; ?>"><?php echo $logic['process']; ?></td>
  </tr>
  <tr>
    <td style="<?php echo $style['tableCell']; ?> font-weight: bold;">输<br>出</td>
    <td style="<?php echo $style['tableCell']; ?>"><?php echo $logic['output']; ?></td>
  </tr>
  <?php endforeach; ?>
  <tbody>
  </tbody>
</table>
<?php endforeach; ?>