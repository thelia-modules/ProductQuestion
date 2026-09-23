# ProductQuestion — plan of work

The state of the module and the decisions it rests on. Read this first when picking the work
back up in a fresh session.

## What the module does

A signed-in customer asks a question about a product. An administrator answers it from the
back office, or turns it down. An answered question, and only an answered one, appears on the
product page, in the language it was asked in.

## Frozen decisions

| Decision | Why |
|---|---|
| Name `ProductQuestion`, table `product_question` | A question is about a product and says so. The foreign key can then cascade, which spares the module the deletion listener a polymorphic `ref`/`ref_id` pair would need. |
| Status as a TINYINT plus a PHP enum, no `product_question_status` table | The three states are a workflow the module implements. No shop administrator creates a fourth, so a table would buy a join on every list, an i18n table for the labels, and fixtures to install and upgrade, for nothing. |
| One `answer`, not a thread | A question gets one reply from the shop. A conversation is a different feature. |
| `answered_at` and `answered_by` alongside the answer | The back office shows who answered and when. Both are set in the same write as the status. |
| `ON DELETE CASCADE` from `product` | A question about a product that no longer exists has no reader. |
| `ON DELETE SET NULL` from `customer` and from `admin` | A published answer belongs to the shop. Closing either account must not take it off the product page. |
| Only a signed-in customer may ask | The back office links to the customer record, and an account is what makes that link exist. `customer_id` is nullable in the database for the SET NULL rule alone. |
| No mention of the answer's author on the product page | Decided with the developer: the answer is the shop's, unsigned. |
| Menu entry under Customers | `hook_block('main.top-menu-customer')`, which the `default-twig` side nav renders. |
| Front office shows the visitor's locale only | A question asked in French is answered in French. |
| No admin API in v1 | Everything goes through the back office. |

## Phases

One commit per phase. Each ends with the module suite green, PHPStan clean, php-cs-fixer
stable, and the developer reviewing before the next one starts.

- [x] **1. Foundation** — repository skeleton, `schema.xml` and the generated SQL, model stubs,
  status enum, activation, quality gate, test harness.
- [x] **2. Domain and hooks** — repository behind a storage contract, the services that ask,
  answer and refuse, the three events, the status catalogue, the text sanitiser, both hooks
  wired and proven in a running shop.
- [ ] **3. Back office** — list and detail controllers, filters and pagination, the answer
  form, ACL, French and English strings. Until this lands, the side-nav entry points at a
  path no controller answers.
- [ ] **4. Front office** — the ask form, the Twig component replacing the plain template,
  the API resource for reading and posting, the rate limiter, the module stylesheet.
- [ ] **5. Administrator notification** — the message, its templates, its translations and
  the listener that sends it.
- [ ] **6. Close** — README, this file brought up to date, full review of the diff.

## Traps this module has already walked into

- `ModuleValidator` refuses a module with no `Config/config.xml`, even though Thelia 3
  discovers hooks, loops, forms and services from the classes themselves. The file is present
  and empty.
- `module:generate:sql` writes `Config/TheliaMain.sql`, not the `thelia.sql` older modules
  carry. It also leaves a `Config/sqldb.map` behind, which is a generator artifact and is
  git-ignored.
- That SQL file starts with `DROP TABLE IF EXISTS`. `postActivation()` runs it once and
  records `is_initialized`, so a later activation leaves the questions alone. Proven by
  inserting a row and re-running `module:post-activate-all`.
- Thelia stores module configuration as strings. `getConfigValue()` returns `?string` and
  `setConfigValue()` takes a string, so the flag is `'1'`, never `true`.
- The back-office translation domain is built from the name of the template directory:
  `productquestion.bo.default-twig`, with the strings in
  `I18n/backOffice/default-twig/`. Renaming one without the other leaves every string
  untranslated and raises nothing. The `Comment` module in this install has that exact
  mismatch, `I18n/backOffice/default` against a `default-twig` template directory.
- The generated setters are natively typed. `status` is a TINYINT, so its setter takes `?int`
  and a `true` is a TypeError. Nothing outside `Model/ProductQuestion.php` touches the
  integer: `setStatusEnum()` and `getStatusEnum()` are the way through.
- php-cs-fixer's `@Symfony` set breaks the boxed header older Thelia modules carry into
  several comment blocks. The module's config declares the header instead, in the short form
  the tool keeps in order.
- Activating the module adds a table to the Propel schema, and the container PHPUnit runs
  against is the non-debug one. Until it is rebuilt, the API facet tests fail in bulk, thirty
  of them, none naming this module. `cache:clear --env=test --no-debug` before running the
  suites is what separates a real regression from that. Proven by an A/B: the same four
  pre-existing failures with the module on and with it off.
- The back-office hook and the front-office hook are two different mechanisms. The front one
  implements `ThemeHookInterface`, is collected through the autoconfigured `thelia.theme_hook`
  tag and needs no row anywhere. The back-office one extends `BaseHook`, and
  `RegisterHookListenersPass` creates its `module_hook` row **while the container is being
  compiled**, per environment. A menu entry that renders in dev is simply absent in test until
  the non-debug test container has been rebuilt once with the module active. That is what a
  first run of the back-office proof looked like: a page that renders, a 200, and no entry.
- PHPStan needs `scanDirectories: var/propel/dev/model` to see the generated Propel classes.
  Without it every accessor on the model stub is reported as undefined.

## Standing outside this module

- `CustomerPersonalDataExporterTest` compares the exported sections to
  `CustomerPersonalDataExporter::CORE_SECTION_NAMES` by strict equality, while that class's
  own comment says modules contribute sections of their own. Any active module that provides
  personal data turns the test red; the `Comment` module already does. Exposing a customer's
  questions here, which the next phase should do, would keep it red. The test is the thing to
  change, not the exporter.

## Proven in a running shop

- The product page of a French visitor carries the answered French question and neither the
  pending one nor the answered English one. The English page carries the English question
  under an English heading. Checked over HTTP against the `work` theme.
- The back-office side nav renders
  `<a href="…/admin/module/product-questions" id="customer_menu_product_question">Customer
  questions</a>` under Customers, in an authenticated request.

## Not proven yet

- No browser has looked at the block: the Chrome extension was not connected, so the checks
  above are HTTP. The front office has no styling of its own yet, which is phase 4.
- `ON DELETE SET NULL` from `customer` has not been exercised against the database: every
  customer in the demo data has an order, and the core's own `fk_order_customer_id` blocks the
  delete. The rule is in `SHOW CREATE TABLE` and in the schema test.
