# Demoing CellarOS

The full walkthrough — with screenshots and a cheatsheet — is
**[`demo-guide.html`](demo-guide.html)** in this folder. Open it in a browser.

This file is the short version, for when you just need the commands.

## Reset the demo

```bash
php artisan demo:reset          # asks first
php artisan demo:reset --force  # doesn't
```

Run it **before** a demo (so you don't inherit the last person's basket) and
**after** one (so the next person doesn't inherit yours).

It removes only the two demo companies and their five private merchants, then
rebuilds them. Real customers, real supplier catalogues and every other tenant's
data are untouched — there's a test for exactly that.

## Logins

Password is `password` for all of them.

| Sign in at  | Account                      | Shows                                             |
|-------------|------------------------------|---------------------------------------------------|
| `/login`    | `demo@cellaros.test`         | Pro — one venue, three merchants, the whole journey |
| `/login`    | `group@cellaros.test`        | Group — two venues, per-venue merchants, a team     |
| `/login`    | `group.member@cellaros.test` | A member scoped to the Riverside venue only         |
| `/admin`    | `admin@cellaros.test`        | Back office: suppliers, parsing, AI costs           |
| `/supplier` | `supplier@cellaros.test`     | The merchant's own portal                           |

## What the demo data is

Fictional merchants (Northbank, Ashgrove, Corvina, Halliwell, Saltmarsh)
carrying **real** wine data — names, producers, regions and grapes sampled from
public trade catalogues this environment has parsed, spread across countries so
the list reads like a merchant's rather than a test fixture. A bare install with
nothing imported falls back to a curated list and still demos.

The merchants are **private to the demo companies**. That's what makes
`demo:reset` safe to run anywhere, keeps the demo invisible to real tenants and
out of golden snapshots, and lets the demo account edit prices, delete wines and
approve parsed lists — all of which are restricted to a company's own private
suppliers.

## What's deliberately arranged

Change any of this in `database/seeders/DemoSeeder.php` and re-run `demo:reset`.

- A FIXED comparison pair — Côtes du Rhône Villages "Les Galets", Domaine de
  la Fontclaire — listed by Northbank at £14.00 and Ashgrove at £11.60. The
  sampled wines produce overlaps too, but their names depend on what this
  environment parsed, so the guide points at this pair instead.
- The same four sampled wines from two merchants at different prices → more
  cross-merchant price comparison.
- One wine held at **POA** with the merchant's own wording.
- Sparkling in four styles plus port and sherry → Type and Style.
- Case-quoted and bottle-quoted lines side by side.
- Three orders across the lifecycle; the **received** one was placed at ~8%
  lower prices, so repeating it shows the change report.
- Two stock lines low enough to trip the dashboard's low-stock alerts.
- A price list awaiting review, with one flagged row that bulk-approve skips.
- Two unmapped type words (`Skin Contact`, `Vin de Voile`) waiting on the
  review screen.
- Portal documents awaiting analysis, analysed, and failed; plus a merchant
  whose invite hasn't been accepted.

## Rebuilding the guide's screenshots

The guide's screenshots are captured from a freshly reset demo. If the UI
changes enough to date them, re-shoot against `demo:reset` state and rebuild
`demo-guide.html` — the page is self-contained (fonts and images are inlined),
so there is nothing else to deploy.
