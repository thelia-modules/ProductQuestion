# ProductQuestion

Lets a signed-in customer ask a question about a product, and an administrator answer it from
the back office. A question appears on the product page once it has been answered, and never
before.

Thelia 3 only: the front office is Twig, the back office runs on the `default-twig` theme, and
the module carries no Smarty template and no loop.

## Requirements

Thelia 3.0 or greater, PHP 8.3.

## Installation

With Composer, from the Thelia root:

```
composer require thelia/product-question-module
```

Manually, copy the module into `<thelia_root>/local/modules/` under the name `ProductQuestion`,
then activate it in the back office.

## Data

One table, `product_question`. A question belongs to one product and, while the account
exists, to one customer.

| Column | Meaning |
|---|---|
| `product_id` | The product the question is about. Deleting the product deletes its questions. |
| `customer_id` | Who asked. Set to null when the account is deleted, so the answer stays on the page. |
| `locale` | The language the question was asked in. The product page shows the visitor's own. |
| `content` | The question. |
| `status` | `0` pending, `1` answered, `2` refused. |
| `answer` | What the shop replied. |
| `answered_at`, `answered_by` | When, and by which administrator. Null when the account is deleted. |

The three statuses are a PHP enum, `ProductQuestion\Model\ProductQuestionStatus`, not a table:
they are a workflow the module implements, not a list a shop administrator can add to.

## Tests

The module's own suite needs neither a database nor a booted kernel:

```
vendor/bin/phpunit -c local/modules/ProductQuestion/phpunit.xml.dist
```

Style and static analysis, from the Thelia root:

```
vendor/bin/php-cs-fixer fix --config=local/modules/ProductQuestion/.php-cs-fixer.dist.php
vendor/bin/phpstan analyse -c local/modules/ProductQuestion/phpstan.neon
```

PHPStan reads the Propel classes from `var/propel/dev/model`, so the module has to have been
activated once for the analysis to see anything beyond the stubs.
