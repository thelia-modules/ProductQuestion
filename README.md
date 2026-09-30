# ProductQuestion

Lets a signed-in customer ask a question about a product. The shop publishes it, answers it
from the back office, and can let other customers answer too. Customers say which answers
helped them, and the most helpful come first. Nothing a customer writes reaches the product
page before a moderator has accepted it.

Thelia 3 only: the front office is Twig, the back office runs on the `default-twig` theme, and
the module carries no Smarty template and no loop.

**Signed-in customers only in this version.** A visitor without an account cannot ask, answer
or vote; the form invites them to sign in. There is no guest question (name and address) and no
anti-robot challenge: the account is what stands in for one. Guest questions behind the
ReCaptcha module are a possible later version.

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

Four tables.

`product_question`: one question about one product.

| Column | Meaning |
|---|---|
| `product_id` | The product. Deleting the product deletes its questions, their answers and votes. |
| `customer_id` | Who asked. Set to null when the account is deleted or anonymized. |
| `locale` | The language it was asked in. The product page shows the visitor's own, or every language when the shop says so. |
| `content` | The question. |
| `status` | `0` pending, `1` published, `2` refused. |
| `helpful_count` | The helpful votes of its published answers, summed: what the page orders questions by. |
| `notify_author` | Whether the author still gets a mail for each new answer. Turned off by the link in those mails. |

`product_question_answer`: the answers of a question. The shop's answer is flagged
`is_official`, one per question, published as it is written. A customer's answer starts pending.
`published_at` is the date of the first publication: it is what tells a first publication, which
the author of the question is told about, from a later one. `customer_id` and `admin_id` are set
to null when the account goes; the answer stays.

`product_question_answer_vote`: one row per customer who found an answer helpful, unique on
(answer, customer), so a vote counts once whatever the number of clicks. Anonymizing a customer
keeps their votes counting and removes their id.

`product_question_closed_product`: one row per product the shop closed to new questions. Deleting
the product deletes its row.

The three statuses are a PHP enum, `ProductQuestion\Model\ProductQuestionStatus`, shared by
questions and answers. `Published` was called `Answered` up to 1.2.0; the old name is kept as a
constant.

## Upgrading from 1.2.0

Run `php Thelia module:refresh` after updating the package. `update()` moves each answer the shop
had published into `product_question_answer` as the official answer of its question, with its
author and date, then drops the three old columns (`answer`, `answered_at`, `answered_by`). A
question refused after it was answered keeps its answer, off the page as before. Nobody is mailed
again: a moved answer counts as already announced. Every step checks the database first, so a
refresh interrupted half way finishes on the next one without copying anything twice. The same
upgrade runs on `postActivation()` when the table is found from an earlier install.

The module's stylesheet is copied under `public/assets/` the first time the page needs it and not
refreshed afterwards: delete `public/assets/frontOffice/<theme>/ProductQuestion/` once after the
upgrade so the new styles are published.

## Front office

The module answers the `product.bottom` theme hook, so a theme calling that hook shows the
block with no further work: a Symfony UX live component rendered in the page, not loaded by a
script. It lists the published questions of the product in the language being browsed, the most
helpful first, each with its published answers: the shop's first, then the customers' by helpful
votes. It carries the form to ask a question, and, when the shop lets customers answer, an
"Answer this question" button under each question. A "This answer helped me" button sits under
each answer. Nothing in the block depends on who is looking beyond the forms, so the page can
still come from a shared cache: a vote is confirmed in a message, not by changing the button.

Settings of the moderation screen change the block; each is off by default, which is the block
described above:

- **Questions per page**: past this number, a "Show more questions" link adds the next page. It is
  a plain link (`?questions_page=2`) the live component intercepts, so it works without
  JavaScript and never loads a page of questions by a script alone.
- **Offer a search above this number of published questions**: the field searches the questions
  and their published answers. A GET form (`?questions_search=`), filtered in place when
  JavaScript runs; "Show more" keeps the term.
- **Show the questions of every language**: each question asked in another language than the
  page's says which ("Asked in French", "Posée en anglais") and carries a `lang` attribute.
- **Close the whole shop to new questions**, and the same switch per product in the **Modules**
  tab of the product edit page: the form and the answer buttons leave the block, a line says the
  product takes no more questions, and the published questions stay. A product with nothing
  published shows nothing at all.

The block costs the product page the same number of queries whatever the number of questions: the
page of questions, their answers, the closed-product lookup, and a count once "Show more" or the
search is on.

The block carries its own stylesheet, `templates/frontOffice/default/assets/product-question.css`,
linked by the hook through `module_asset()`. Type scale and colours are the theme's classes.

Budgets guard the writes, declared by the module itself: for questions, twenty per address per
hour, ten per customer, three per customer and product; for answers, twenty per address and ten
per customer per hour. A text that fails validation spends none of them. A vote counts once per
customer and answer, and nobody votes for their own answer.

## Front API

For a front office that talks to the API rather than to Twig:

| Operation | Who | What |
|---|---|---|
| `GET /api/front/product_questions?productId=&locale=&search=` | anyone | The published questions of one product, in one language (when `locale` is absent: the request's if the shop has it, the shop's default language otherwise, every language when the shop shows them all), with their published answers in `answers`. `search` is applied above the shop's search threshold and ignored below it, as on the product page. `answer` and `answeredAt` still carry the shop's answer, as in 1.2.0. Paginated (`page`, `itemsPerPage`, at most 100). A parameter given as an array is a 400. |
| `GET /api/front/product_questions/{id}` | anyone | One published question. A pending or refused one is a 404. |
| `POST /api/front/account/product_questions` | a signed-in customer (JWT) | `{"productId": 12, "content": "…", "locale": "fr_FR"}`; without `locale`, the question takes the request's language if the shop has it, the shop's default language otherwise. Answers 201 with `published: false`, 422 on an invalid text, on a language the shop does not have or on a product that does not exist or is offline, 403 on a closed product or shop, 429 past the budgets. |
| `GET /api/front/product_question_answers/{id}` | anyone | One published answer of a published question. |
| `POST /api/front/account/product_question_answers` | a signed-in customer (JWT) | `{"questionId": 5, "content": "…"}`. 201 with `published: false`; 422 when the shop takes no customer answers or the question is not on the page; 403 when its product or the shop is closed; 429 past the budgets. |
| `POST /api/front/account/product_question_answers/{id}/helpful` | a signed-in customer (JWT) | No body. 200 with the answer and its count, counted once per customer; 404 for an answer off the page or one's own. |

Neither the customers who asked or answered nor the administrator who answered is in any payload.

## Notification

Every new question sends one mail to the shop's notification addresses (Configuration > Store
information), in the shop's language: who asked, about which product, the text, and a link to
the moderation screen. The message is `product_question_notification_admin`, editable like any
other in Configuration > Mailing templates; its templates live in `templates/email/default/`.
A shop with no notification address gets no mail and one line in the Thelia log; the question
is stored either way.

The author of a question is told once per answer, when it is first published, in the language
they asked in: `product_question_answered_customer` for the shop's answer,
`product_question_answered_by_customer` for another customer's. Rewriting an answer, or
publishing it again after a refusal, sends nothing, nor does an author's own answer.

Each of these mails carries a link to stop them for that question: signed with the application
secret and valid for 90 days, with nothing stored. Opening it shows a page with one button, so a
mail scanner following every link does not unsubscribe anyone; the button sets `notify_author`
off. An altered or expired link is refused and changes nothing.

A shop that activated an earlier version gets the messages it lacks on `php Thelia module:refresh`.

## Back office

The module adds a **Customer questions** entry at the first level of the side navigation,
after the entries of the Page, TheliaBlocks and Option modules. It is contributed through the
`main.in-top-menu-items` hook at position 4, whose `module_hook` row is created when the
container is compiled, so the entry appears once the cache has been rebuilt with the module
active. A badge on it counts the questions and customer answers waiting for a moderator: two
indexed counts, run only when the menu is drawn.

The entry opens the moderation list, at `/admin/module/ProductQuestion`, which is also where the
**Configure** button of the module list leads. It has one tab per status with a count, an
**Answers waiting** tab for the questions with a customer answer to moderate, filters on
language, product, customer and sort order, and pagination. Ticking questions, or the box of the
header for the whole page, and choosing **Publish**, **Refuse** or **Delete the selection**
applies the decision to each, with one line per question in the administration log; deleting
asks for a confirmation. The settings sit above the list, all off by default: customer answers,
the shop-wide closing, the questions of every language, the page size and the search threshold.

Opening a question shows it in full, links to the customer record and to the product, and
carries the textarea the shop answers with. Publishing the shop's answer puts the question on the
product page; **Publish without answering** puts it there with no answer, for customers to
answer. Refusing takes it off without erasing what was written. Customer answers are listed under
it with their status and votes, each with Publish, Refuse and Delete.

Every change takes the session token and leaves a line in the administration log, under the
module and the question. Access is granted on the module itself, so a profile can be given these
screens and nothing else of the back office.

## Personal data

The core's export of a customer's personal data carries their questions, the answers they wrote
and the answers they voted for, under `product_question`. Anonymizing the customer cuts the link
from all three; the texts stay on the product page and the votes keep counting.

## Tests

Unit tests need neither a database nor a booted kernel:

```
vendor/bin/phpunit -c local/modules/ProductQuestion/phpunit.xml.dist
```

Integration tests (repository, upgrade from 1.2.0, front API, unsubscribe link, moderation
screens, personal data) run on the install's test database, through its root configuration:

```
php bin/test-prepare
vendor/bin/phpunit local/modules/ProductQuestion/Tests
```

The upgrade test creates and drops a scratch database, `test_product_question_upgrade`, next to
the test one: the database user needs the right to create it.

Style and static analysis, from the Thelia root:

```
vendor/bin/php-cs-fixer fix --config=local/modules/ProductQuestion/.php-cs-fixer.dist.php
vendor/bin/phpstan analyse -c local/modules/ProductQuestion/phpstan.neon
```

PHPStan reads the Propel classes from `var/propel/dev/model`, so the module has to have been
activated once for the analysis to see anything beyond the stubs.
