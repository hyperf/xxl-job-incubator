## PHP Swagger Api Docs

基于 [Hyperf](https://github.com/hyperf/hyperf) 框架的 DTO 类映射

##### 优点

- 请求参数映射到PHP类
- 代码可维护性好，扩展性好
- 支持数组，递归，嵌套
- 支持框架数据验证器

##### 缺点

- 模型类需要手工编写

## 注意

> php >= 8.0

## 安装

```
composer require tangwei/dto
```

## 使用

### 1. 使用

## 注解

> 命名空间:`Hyperf\DTO\Annotation\Contracts`

#### RequestBody

- 获取Body参数

```php
public function add(#[RequestBody] DemoBodyRequest $request){}
```

### RequestQuery

- 获取GET参数

```php
public function add(#[RequestQuery] DemoQuery $request){}
```

### RequestFormData

- 获取表单请求

```php
public function fromData(#[RequestFormData] DemoFormData $formData){}
```

- 获取文件(和表单一起使用)

```php
#[ApiFormData(name: 'photo', type: 'file')]
```

- 获取Body参数和GET参数

```php
public function add(#[RequestBody] DemoBodyRequest $request, #[RequestQuery] DemoQuery $query){}
```

> 注意: 一个方法，不能同时注入RequestBody和RequestFormData

## 示例

### 控制器

```php
#[Controller(prefix: '/demo')]
#[Api(tags: 'demo管理', position: 1)]
class DemoController extends AbstractController
{
    #[ApiOperation(summary: '查询')]
    #[PostMapping(path: 'index')]
    public function index(#[RequestQuery] #[Valid] DemoQuery $request): Contact
    {
        $contact = new Contact();
        $contact->name = $request->name;
        var_dump($request);
        return $contact;
    }

    #[PutMapping(path: 'add')]
    public function add(#[RequestBody] DemoBodyRequest $request, #[RequestQuery] DemoQuery $query)
    {
        var_dump($query);
        return json_encode($request, JSON_UNESCAPED_UNICODE);
    }

    #[PostMapping(path: 'fromData')]
    public function fromData(#[RequestFormData] DemoFormData $formData): bool
    {
        $file = $this->request->file('photo');
        var_dump($file);
        var_dump($formData);
        return true;
    }

    #[GetMapping(path: 'find/{id}/and/{in}')]
    public function find(int $id, float $in): array
    {
        return ['$id' => $id, '$in' => $in];
    }

}

```

## 验证器

### 基于框架的验证

> 安装hyperf框架验证器[hyperf/validation](https://github.com/hyperf/validation), 并配置(已安装忽略)

- 注解
  `Required` `Between` `Date` `Email` `Image` `Integer` `Nullable` `Numeric`  `Url` `Validation`
- 校验生效

> 只需在控制器方法中加上 #[Valid] 注解

```php
public function index(#[RequestQuery] #[Valid] DemoQuery $request){}
```

```php
class DemoQuery
{
    public string $name;

    #[Required]
    #[Integer]
    #[Between(1,5)]
    public int $num;
}
```

- Validation

> rule 支持框架所有验证
- 自定义验证注解
> 只需继承`Hyperf\DTO\Annotation\Validation\BaseValidation`即可
```php
#[Attribute(Attribute::TARGET_PROPERTY)]
class Image extends BaseValidation
{
    protected $rule = 'image';
}
```

## 注意

```php
    /**
     * 需要绝对路径.
     * @var \App\DTO\Address[]
     */
    #[ApiModelProperty('地址')]
    public array $addressArr;
```

- 映射数组类时,`@var`需要写绝对路径
- 控制器中使用了框架`AutoController`注解,只收集了`POST`方法

