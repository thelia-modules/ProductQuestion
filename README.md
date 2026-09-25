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

Deleting the module keeps the questions unless the shop asks to delete the module's data too;
in that case the table and the two mail messages go with it. Reinstalling after a deletion
that kept the data finds the questions where they were.

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

## Front office

The module answers the `product.bottom` theme hook, so a theme calling that hook shows the
block with no further work: a Symfony UX live component with the answered questions of the
product in the language being browsed, and the form to ask one. The form is drawn for a
signed-in customer only; a visitor is invited to sign in. A question starts pending and
appears once the shop has answered it.

The block carries its own stylesheet, `templates/frontOffice/default/assets/product-question.css`,
linked by the hook through `module_asset()`. Type scale and colours are the theme's classes.

Three budgets guard the ask, declared by the module itself: twenty questions per address per
hour, ten per customer, three per customer and product. A question that fails validation spends
none of them.

## Front API

For a front office that talks to the API rather than to Twig:

| Operation | Who | What |
|---|---|---|
| `GET /api/front/product_questions?productId=&locale=` | anyone | The answered questions of one product, in one language (the request's when `locale` is absent). Paginated (`page`, `itemsPerPage`, at most 100). A parameter given as an array is a 400. |
| `GET /api/front/product_questions/{id}` | anyone | One answered question. A pending or refused one is a 404. |
| `POST /api/front/account/product_questions` | a signed-in customer (JWT) | `{"productId": 12, "content": "…", "locale": "fr_FR"}`. Answers 201 with `published: false`, 422 on an invalid text or on a product that does not exist or is offline, 429 past the budgets. |

Neither the customer who asked nor the administrator who answered is in any payload.

## Notification

Every new question sends one mail to the shop's notification addresses (Configuration > Store
information), in the shop's language: who asked, about which product, the text, and a link to
the moderation screen. The message is `product_question_notification_admin`, editable like any
other in Configuration > Mailing templates; its templates live in `templates/email/default/`.
A shop with no notification address gets no mail and one line in the Thelia log; the question
is stored either way.

The customer is told once, when the shop first publishes its answer: their question, the
answer and a link to the product page, in the language they asked in. Rewriting the answer
later sends nothing. The message is `product_question_answered_customer`.

A shop that activated an earlier version gets the messages it lacks on `php Thelia module:refresh`.

## Back office

The module adds a **Customer questions** entry at the first level of the side navigation,
after the entries of the Page, TheliaBlocks and Option modules. It is contributed through the
`main.in-top-menu-items` hook at position 4, whose `module_hook` row is created when the
container is compiled, so the entry appears once the cache has been rebuilt with the module
active.

The entry opens the moderation list, at `/admin/module/ProductQuestion`, which is also where the
**Configure** button of the module list leads. It has one tab per
status with a count, filters on language, product, customer and sort order, and pagination.
Opening a question shows it in full, links to the customer record and to the product, and
carries the textarea the shop answers with. Publishing an answer puts the question on the
product page. Refusing takes it off without erasing what was drafted.

Access is granted on the module itself, so a profile can be given these screens and nothing
else of the back office.

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
