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
| Top-level menu entry, right after Option | `main.in-top-menu-items`, position 4. The side nav folds every section's sub-entries behind a click, so an entry filed under Customers was invisible until that section was opened: the developer asked for it at the first level, after the content blocks and the Options entry. |
| Front office shows the visitor's locale only | A question asked in French is answered in French. |
| No admin API in v1 | Everything goes through the back office. |
| Admin screens live at `/admin/module/ProductQuestion` | The module has nothing to configure, so the **Configure** button of the module list, which points at that URL, opens the moderation list instead of an empty configuration page. The module route shadows the core `admin.module.configure` route for this one code. |

## Phases

One commit per phase. Each ends with the module suite green, PHPStan clean, php-cs-fixer
stable, and the developer reviewing before the next one starts.

- [x] **1. Foundation** — repository skeleton, `schema.xml` and the generated SQL, model stubs,
  status enum, activation, quality gate, test harness.
- [x] **2. Domain and hooks** — repository behind a storage contract, the services that ask,
  answer and refuse, the three events, the status catalogue, the text sanitiser, both hooks
  wired and proven in a running shop.
- [x] **3. Back office** — moderation list with status tabs, filters and pagination, the
  question screen with its answer form, refuse and delete, access granted on the module,
  French and English strings.
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
- A hook may declare `'position' => N` next to its method. RegisterHookListenersPass reads it once,
  when it creates the `module_hook` row, and orders listeners by ascending position; after that
  the order belongs to the back office. The pass also deletes a row whose method no longer
  exists on the class, which is how the first `main.top-menu-customer` entry went away on its
  own when the method was renamed.
- The back-office hook and the front-office hook are two different mechanisms. The front one
  implements `ThemeHookInterface`, is collected through the autoconfigured `thelia.theme_hook`
  tag and needs no row anywhere. The back-office one extends `BaseHook`, and
  `RegisterHookListenersPass` creates its `module_hook` row **while the container is being
  compiled**, per environment. A menu entry that renders in dev is simply absent in test until
  the non-debug test container has been rebuilt once with the module active. That is what a
  first run of the back-office proof looked like: a page that renders, a 200, and no entry.
- A module back-office template includes its partials by a path relative to its own
  back-office directory, `ProductQuestion/_table.html.twig`. The `@ProductQuestionModule`
  namespace points at the module's `templates/` root instead, so the namespaced form of the
  same include is a loader error at render time and nowhere else.
- `Thelia\Form\BaseForm::getView()` reads a property nothing has initialised unless the form
  went through the framework's own rendering path. A controller hands a view to Twig with
  `getForm()->createView()`.
- `checkAuth()` answers a 403 error page, never a redirect to the login form, so an anonymous
  visitor gets 403 on every module admin screen. `/admin/module/comments` does the same. The
  access is refused either way; only the courtesy differs.
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
- The back-office side nav renders the entry at the first level, right after Pages, Blocs de
  contenus and Options, in an authenticated request against the dev database; the entry reads
  « Questions client » when the interface is French and is marked active on the module's own
  screens. The Customers section is a plain link again.
- The moderation screens, in authenticated requests against a real database: the list shows a
  question with its author and status, the status filter keeps only what it asks for, the
  answer form publishes and writes the four columns, a post with no CSRF token changes
  nothing, and refusing moves the status while leaving the drafted answer in place.
- The whole chain in one run: a pending question renders nothing on the product page, an
  administrator answers it through the back-office form, and the same product page then
  carries the question and the answer.
- An anonymous request to the moderation list is refused and leaks no question text.

## Not proven yet

- No browser has looked at any screen: the Chrome extension was not connected, so every check
  above is HTTP or a kernel request. Nothing is said about layout, and the front office has no
  styling of its own yet, which is phase 4.
- The back-office screens have no permanent regression test. The proofs above ran from a
  temporary test inside the Thelia repository, which was deleted afterwards: the module's own
  suite boots no kernel, so it cannot hold them. Giving the module a second, kernel-backed
  test suite is the obvious next step and is not done.
- `ON DELETE SET NULL` from `customer` has not been exercised against the database: every
  customer in the demo data has an order, and the core's own `fk_order_customer_id` blocks the
  delete. The rule is in `SHOW CREATE TABLE` and in the schema test.
