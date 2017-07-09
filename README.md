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
generate/admin-site-functional-document |  产品业务中心功能文档生成 
generate/database-document |  产品业务中心数据库文档生成 
github/pull-organizations |  拉取Github的组织信息并且存储到本地数据库中。 
github/pull-users |  拉取Github的用户信息并且存储到本地数据库中。 
help |  显示帮助信息 
ip-pool/hunt |  抓取代理IP信息并保存到IP池中。 
ip-pool/verify-pool |  验证IP池中的代理IP，并清除掉不可用的代理。 
refresh-readme |  根据命令更新Readme.md文件 
shgt/move-local-service-to-remote-service |  将本地服务移动到远程服务中。 
suanhetao/web-service/generate-model-and-curd |  根据指定的数据库表生成模型与数据库操作动作。 
