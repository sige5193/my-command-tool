<?php 
$vars = get_defined_vars();
$commands = $vars['commands'];
?>
# My Command Tool
My Command Tool 是一个使用PHP写的命令行工具集合， 主要目的是为了平时重复性工作的替代与数据的抓取等。

## 使用方式
```
$ php index.php [command] [params]
```

例如：

```
$ php index.php github/find-project-by-name --name=test --order=starts
```

## Todo
- [ ] 对于像数据抓取这种操作，将进行分布式支持。

## 命令列表
名称 | 描述
------------ | -------------
<?php foreach ( $commands as $command ) : ?>
<?php echo $command['name']; ?> | <?php echo $command['description']; ?> <?php echo "\n";?>
<?php endforeach; ?>