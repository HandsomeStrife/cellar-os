# UK & Ireland Wine Trade Suppliers — Master Sourcing Reference

> Research date: **10 June 2026**. 64 suppliers verified across two passes: a deep
> adversarial-verification pass (8 suppliers, 3-vote claim checking, marked ◆◆) and a
> breadth sweep (56 suppliers, every claim from actually-loaded pages, marked ◆; ○ =
> search-snippet only). Machine-readable copy: [`uk-wine-trade-suppliers.json`](uk-wine-trade-suppliers.json)
> — usable later to seed Listed suppliers in `/admin/suppliers`.
> All access checks are point-in-time; these surfaces churn (Matthew Clark's old public
> PDFs already 404; flipbook URLs rotate per edition).

## Headline: where the parseable lists are

**Nobody publishes live trade pricing openly as policy** — but six suppliers publish priced
documents anyway, and they are the pipeline's Tier-A targets:

| Priority | Supplier | What | Why it matters |
|---|---|---|---|
| 1 | **Farr Vintners** | Live stock list as **XLSX / CSV / TXT / JSON** (`farrvintners.com/downloads`) with GBP prices, scores, product URLs | Machine-readable, continuously generated from their DB — no parsing needed beyond a column mapping |
| 2 | **Flint Wines** | Two **priced xlsx** files (inventory + broking list), dated filenames (`pc-inventory-19052026.xlsx`), "last updated 19 May 2026" | Clean tabular path; IB per bottle/case prices |
| 3 | **Enotria** | **Priced Fine Wine List xlsx** (April 2026) on a public downloads page + annual Wine List flipbook | Tabular path; national-distributor coverage |
| 4 | **Tanners Wines** | `trade_list_2026.pdf` — per-wine ex-VAT prices verified by text extraction; annual + mid-June price review | PDF path (pattern-parse candidate) |
| 5 | **Hallgarten & Novum** | Trade Price List flipbooks (FlipHTML5, Download button; editions 2021–2025, 2026 prices from 1 Mar) | PDF-via-flipbook; annual cadence |
| 6 | **Clark Foyster** | Priced wine list PDF (Feb 2025; case+bottle prices) | PDF path |

Strong unpriced-but-parseable artefacts (portfolio data, no prices): Les Caves de Pyrene
(406pp stable-URL PDF, superseding dated editions), Alliance Wine (Spring 2026 portfolio
PDF), Kingsland (UK Stock 2026-27 PDF), Goedhuis Waddesdon (Commercial Collection 2026 PDF),
Astrum (dynamic PDF), Inverarity Morton (Flipsnack with underlying PDF), Cassidy Wines IE
(112pp PDF), Buckingham Schenk (53pp 2026 PDF), Adnams (Jan 25 list), Louis Latour Agencies
(brochure), Raeburn (see below).

**Raeburn correction (important):** both public Raeburn PDFs — the WEB-List *and* the
"WEB-TARIFF April 2026" — are **price-stripped in their WEB editions** (verified by full-text
extraction; the tariff name is misleading). Priced tariffs are sent by email on request
("just tell us which you want to receive"). Cadence is strong: dated editions roughly every
1–3 months + a Burgundy en primeur offer each Jan/Feb. The priced trade xls being sourced
remains the right route.

## Patterns across all 64

- **Priced & public: 6/64 (9%)** — almost all mid-size or fine-wine houses; the big
  nationals never publish prices (Bibendum/Matthew Clark/Liberty gate behind portals).
- **Public but unpriced: 12/64 (19%)** — portfolio PDFs/catalogues, often *deliberately*
  price-stripped ("generic" editions: Alliance, Raeburn WEB).
- **Partial (web catalogue / flipbook only): 30/64 (47%)** — the dominant model. Flipbooks
  (FlippingBook, FlipHTML5, Calaméo, Issuu, Flipsnack) recur constantly; several have
  extractable underlying PDFs.
- **Fully gated: 16/64 (25%)** — email/form request (most specialists) or login portals
  (the nationals: bibendum-wine-online, matthewclarklive, my.boutinot, trade.hatchmansfield,
  Dynamic Vines trade login).
- **Cadence:** annual editions dominate the nationals (often with a spring price review,
  e.g. Hallgarten 1 Mar, Tanners 15 Jun); fine-wine houses update continuously (Farr live;
  Flint dated snapshots) or by en primeur campaign (Jan/Feb Burgundy, May/Jun Bordeaux);
  specialists are ad-hoc/unknown.
- **Strategic implication (validated by both passes):** priced data at scale will arrive via
  **buyer-uploaded lists from their own trade accounts** — CellarOS's existing
  `/suppliers/{uuid}/documents` flow — with the Tier-A public files as seed catalogues.

## Pipeline onboarding priority

1. **Farr Vintners CSV/JSON** — wire as a fetch-to-tabular source (column mapping only).
2. **Enotria + Flint xlsx** — tabular path as-is.
3. **Tanners / Clark Foyster / Hallgarten PDFs** — pattern-parse candidates (study once, free re-parses).
4. **Unpriced portfolio PDFs** (Les Caves, Alliance, Kingsland, Goedhuis, Astrum, Cassidy…) —
   parse for catalogue/producer data; prices arrive later via buyer uploads.
5. **Flipbook-only suppliers** — investigate underlying PDF asset URLs per platform before
   building anything (Inverarity's Flipsnack exposes one; FlippingBook varies).
6. **Gated suppliers** — rely on buyer uploads; collect trade-registration links in-app so
   buyers can request their own access (links in the table below).

## Open questions

- What format do gated suppliers deliver priced lists in once an account exists (xlsx
  attachment, portal CSV export, per-customer PDF)? Determines real pipeline value.
- Can Les Caves' separately-held priced list be obtained in structured form, and at what cadence?
- Are flipbook platforms' underlying assets stable enough to ingest, or screenshot/OCR-grade only?

## Supplier tables

Legend — **V**: ◆◆ deep-verified (3-vote adversarial) · ◆ page-loaded verification · ○ search-only.

### National distributors (11)

| Supplier | Location | Focus | Public list? | Format & evidence | Cadence | Access | V |
|---|---|---|---|---|---|---|---|
| [Enotria (formerly Enotria&Coe)](https://www.enotria.com/) | London (Park Royal), NW10 area | National distributor of wine and spirits with a dedicated fine wine arm; on… | **PRICED, public** | xlsx (Fine Wine List, priced) + FlippingBook web flipbook (main Wine List 2026) … — _Enotria_Fine_Wine_List_April_2026_915553b20f.xlsx (HTTP 200, Last-Modified 21 Apr 2026, co…_ | Main wine list annual ('Wine List 2026', prior 'Wine Li… | 'New Customer?' button to contact form on enotria.com, or phone +44 (0)20 8961 4411 | ◆ |
| [Hallgarten & Novum Wines](https://www.hnwines.co.uk/) | Luton, Bedfordshire | National distributor (on-trade and independent merchants); publishes separa… | **PRICED, public** | FlipHTML5 flipbook with a Download button (PDF); separate Trade and Indie Mercha… — _Trade Price List 2025 flipbook published 23 Apr 2025 (199pp, Download button present); Ind…_ | Annual (price list editions 2021, 2022, 2023, 2024, 202… | Main site hnwines.co.uk is age-gated (no registration URL captured); trade contact via sales em… | ◆ |
| [Alliance Wine](https://www.alliancewine.com/) | 7 Beechfield Road, Willowyard Estate, Beith, … | Independent UK importer-distributor (est. 1984), 300+ producers from 20+ co… | Public, unpriced | PDF (A5 portfolio, ~5.7MB, 128+ pages by country) — _Filename 'aw_spring-price-list-2026_a5-generic.pdf'; cover reads 'Spring Portfolio 2026'; …_ | Seasonal editions implied by 'Spring ... 2026' naming; … | Contact form at https://www.alliancewine.com/contact-us (no trade portal or registration form f… | ◆ |
| [Buckingham Schenk (now trading as Schenk…](https://schenkfamily-wine.co.uk/) | Unit 5 The E Centre, Easthampstead Road, Brac… | Schenk Group (75%) owned importer est. 1974; ~75% Schenk-produced wines plu… | Public, unpriced | PDF (53pp, 29MB) + Calameo flipbook viewer — _SchenkFamilyUKWineList2026WEB.pdf uploaded 2026/01, PDF metadata created 29 Jan 2026; titl…_ | Annual (inferred from yearly 'Wine List 2026' edition n… | General contact form/email; no dedicated trade portal or registration found on site | ◆ |
| [Kingsland Drinks](https://www.kingsland-drinks.com/) | The Winery, Fairhills Road, Irlam, Manchester… | Employee-owned bottler/contract-packer and distributor (UK Stock range + ex… | Public, unpriced | PDF brochure (12.6MB, 'UK Stock 2026-27') — _Cover 'UK STOCK 2026-27'; URL path /uploads/2026/01/; filename 'Kingsland-Brochure-26-27.p…_ | Annual edition implied by '26-27' naming and yearly upl… | https://www.kingsland-drinks.com/new-business-enquiry/ (new business enquiry form); no public t… | ◆ |
| [Berkmann Wine Cellars](https://www.berkmann.co.uk/) | 70 Rosebery Avenue, London EC1R 4RR (also Har… | Largest family-owned UK wine importer; 1,400+ wines; On-Trade, Off-Trade an… | Partial (web/flipbook) | Calaméo web flipbook (read online; no PDF/xlsx download links found on berkmann.… — _'Berkmann Wine Cellars Portfolio 2024/25' on Calaméo (https://www.calameo.com/books/005541…_ | unknown ('2024/25' season-style title suggests annual p… | Contact form at https://www.berkmann.co.uk/contact/ with a 'Trade' dropdown option; no dedicate… | ◆ |
| [Bibendum](https://www.bibendum-wine.co.uk/) | London (C&C Group; Matthew Clark Bibendum Ltd… | National premium distributor (on-trade focus) | Partial (web/flipbook) | FlippingBook web flipbook (2025 Trade List, public) — _'Bibendum Trade List 2025' flipbook title_ | Annual editions | https://www.bibendum-wine.co.uk/open-an-account/ (form, account-manager follow-up); portal bibe… | ◆◆ |
| [Boutinot Wines](https://boutinot.com/) | Boundary House, Cheadle Point, Cheadle, Chesh… | Producer-distributor (own winemaking in France/South Africa etc. plus agenc… | Partial (web/flipbook) | Public web catalogue (unpriced) + gated trade ordering portal | Annual - FAQ states 'Main price lists are issued once a… | Contact form at https://boutinot.com/contact-us/ has a 'Become a Trade Customer' enquiry type; … | ◆ |
| [Liberty Wines](https://www.libertywines.co.uk/) | 6 Timbermill Way, London SW4 6LY | Premium national distributor; ~2,000 wines from ~400 producers across 26 co… | Partial (web/flipbook) | Web catalogue (filterable portfolio, no file downloads) | unknown | Contact form at https://www.libertywines.co.uk/contact (has 'existing customer?' field) or phon… | ◆ |
| [Matthew Clark](https://www.matthewclark.co.uk/) | Bristol BS99 6ZZ (same legal entity as Bibend… | Full national distributor; ~1,261+ wines, 300+ producers | Partial (web/flipbook) | Filterable web catalogue (42pp, print/download of filtered results, NO prices) — _2024 wine list = price-free FlippingBook; older public PDFs now 404_ | Annual editions | https://www.matthewclark.co.uk/open-an-account/ (bank details, AWRS, 2x ID); portal matthewclar… | ◆◆ |
| [North South Wines](https://northsouthwines.co.uk/) | Drayton Hall, Church Road, West Drayton UB7 7… | B Corp certified UK distributor/importer (est. 2014) of sustainable, family… | Partial (web/flipbook) | FlipHTML5 flipbook ('Independents Brochure') + public web catalogue at wines.nor… — _Flipbook title 'North South Wines Independents Brochure' carries no date; no dated filenam…_ | unknown | https://northsouthwines.co.uk/become-a-customer/ - instructs to use the contact form or phone, … | ◆ |

### Agency importers (14)

| Supplier | Location | Focus | Public list? | Format & evidence | Cadence | Access | V |
|---|---|---|---|---|---|---|---|
| [Astrum Wine Cellars](https://www.astrumwinecellars.com/) | Mitcham, London (14 Wandle Way, CR4 4FG) | Specialist Italian importer (90+ producers) plus Central European wines and… | Public, unpriced | PDF (dynamically generated, ~11MB) — _Direct download confirmed 2026-06-10: HTTP 200, content-disposition filename "wine-catalog…_ | Generated on demand (timestamped filename implies alway… | Priced wine list gated behind account login at https://www.astrumwinecellars.com/login/; new tr… | ◆ |
| [Hatch Mansfield](https://www.hatchmansfield.com/) | Ascot, Berkshire | Premium agency importer (~26 estates: Taittinger, Jadot, Chapoutier, CVNE, … | Public, unpriced | FlippingBook e-catalogue (228pp, zero prices) — _'Hatch Mansfield 2026 E-Catalogue'_ | Annual catalogue | Login-gated trade portal trade.hatchmansfield.com (register interest) | ◆◆ |
| [Louis Latour Agencies](https://www.louislatour.co.uk/) | 12-14 Denman Street, London W1D 7HJ (address … | Independent agency importer: Maison Louis Latour, Champagne Gosset, Simonne… | Public, unpriced | PDF portfolio brochure (confirmed: fetched archived copy, pdftotext shows zero p… — _Filename "LLA-Brochure Dec2023_Final_Digital_SinglePages"; link title "LLA Brochure 2024";…_ | unknown | Trade customer login at https://www.louislatour.co.uk/trade-resources/customer-login (under /tr… | ◆ |
| [Armit Wines](https://www.armitwines.co.uk/) | The Triangle, 2nd Floor, 5-17 Hammersmith Gro… | Fine wine importer/agency (Italy-strong portfolio) supplying restaurants, h… | Partial (web/flipbook) | web catalogue with GBP prices; price list file by email only — _None found - no dated filenames; 622 retail wines live on /wine-list (seen 2026-06-10)_ | unknown | Trade page https://www.armitwines.co.uk/services-events/trade: 'REQUEST AN ACCOUNT FORM' button… | ◆ |
| [Condor Wines](https://condorwines.co.uk/) | Leeds, West Yorkshire (per LinkedIn/directori… | Independent specialist importer of Argentina, Chile and Uruguay (IWC Specia… | Partial (web/flipbook) | Public web catalogue (146 wines, filterable incl. RRP bands) + per-wine tech-she… | unknown | Contact form at https://condorwines.co.uk/home/contact-us/ or email; no trade portal/registrati… | ◆ |
| [Daniel Lambert Wines](https://daniellambert.wine/) | Unit 9 Brynmenyn Business Centre, St Theodore… | Agency importer/wholesaler, French specialist (Best French Importer 2024-26… | Partial (web/flipbook) | Shopify web catalogue (collections by country/region); no PDF/xlsx downloads fou… | unknown | Shopify customer account login/registration at https://daniellambert.wine/account (redirects to… | ◆ |
| [John E. Fells & Sons (Fells)](https://fells.co.uk/) | Kings Langley, Hertfordshire, UK (founded Lon… | Agency importer/distributor for family-owned producers: Symington ports (Gr… | Partial (web/flipbook) | web catalogue (JS-driven, no prices); no downloadable list found — _Only dated PDF on site is unrelated: Modern-Slavery-Statement-March-26.pdf; no dated wine/…_ | unknown | "Trade Login" link in fells.co.uk nav (href is '/', appears to be a JS modal, no separate porta… | ◆ |
| [Maltby & Greek](https://www.maltbyandgreek.com/) | Bermondsey, London (Spa Terminus) | Leading UK importer/distributor of Greek wine, food, spirits and beer, ~70%… | Partial (web/flipbook) | Web shop catalogue (retail prices); no downloadable trade file found | unknown | Wholesale ordering portal at wholesale.maltbyandgreek.com (login for existing trade accounts); … | ○ |
| [Mentzendorff & Co](https://mentzendorff.co.uk/) | London, UK (The Woolyard, Bermondsey area per… | Full-service agency importer: Champagne Bollinger and Ayala, Fladgate ports… | Partial (web/flipbook) | image-based Brand Booklet hosted as a Google Photos shared album + web portfolio… — _Wayback snapshot 24 Jan 2026 shows the Brand Booklet page; no dated filenames anywhere_ | unknown | No trade portal/login on site; route is direct email/phone: orders@mentzendorff.co.uk, 020 7840… | ◆ |
| [Pol Roger Portfolio (Pol Roger Ltd)](https://polroger.co.uk/) | Head office: Shelton House, 4 Coningsby Stree… | Trade-only agency: Champagne Pol Roger plus ~21 fine wine/spirit houses inc… | Partial (web/flipbook) | web catalogue by region/producer, no prices, no downloadable files (full homepag… — _T&Cs reference the "Company's published price list current at the date of acceptance of th…_ | unknown | Contact form https://polroger.co.uk/contact-us-form/ (no trade login/portal on site); annual tr… | ◆ |
| [Roberson Wine](https://robersonwine.com/) | London Cru Winery, 21-27 Seagrave Road, Londo… | Independent London importer-retailer (est. 1991) with a trade arm supplying… | Partial (web/flipbook) | web catalogue | unknown | No public trade portal or registration form found; trade buyers go via the contact page (https:… | ◆ |
| [Fields, Morris & Verdin (FMV)](https://www.fmv.co.uk/) | London, UK (contact page lists Berry Bros. & … | Agency/distribution arm of Berry Bros. & Rudd; exclusive fine-wine producer… | Gated | none public (web producer directory only, no wines or prices; Reports page is ed… | unknown | No public registration form found; contact order@fmv.co.uk or sales team via https://www.fmv.co… | ◆ |
| [Passione Vino](https://passionevino.co.uk/) | Shoreditch, London (plus Exmouth Market site) | Boutique Italian specialist importer/wholesaler (est. 2003) with shop/bar; … | Gated | Form-gated download (format unstated); separate retail Shopify shop | unknown | Trade page form at https://passionevino.co.uk/trade/ ("Fill in your details to download our lat… | ◆ |
| [Top Selection](https://topselection.co.uk/) | London (23 Cellini Street, SW8 2FQ) | Premium importer with exclusive agencies (one producer per region), est. 20… | Gated | Web producer directory only; occasional one-off offer PDFs — _One-off priced offer PDF confirmed live 2026-06-10: https://topselection.co.uk/wp-content/…_ | unknown | No trade portal or registration form found; contact form at https://topselection.co.uk/contact-… | ◆ |

### Regional & mid-size merchants (7)

| Supplier | Location | Focus | Public list? | Format & evidence | Cadence | Access | V |
|---|---|---|---|---|---|---|---|
| [Tanners Wines](https://www.tanners-wines.co.uk/) | Shrewsbury, Shropshire, UK (head office 26 Wy… | Family regional merchant (est. 1842) with a dedicated trade arm: 1,100+ win… | **PRICED, public** | PDF — _trade_list_2026.pdf (header 'TRADE LIST 2026/27'); annual_price_review_26.pdf ('revised pr…_ | Annual trade list (2026/27) + annual price review appli… | https://www.tanners-wines.co.uk/pages/trade-sales (no self-serve portal; contact Area Sales Man… | ◆ |
| [Adnams](https://adnams.co.uk/) | Southwold, Suffolk, UK | Brewer-distiller-merchant supplying own beers/spirits plus a directly impor… | Public, unpriced | PDF — _Filename contains 'Trade_Retail_Adnams_Wine_List_-_Jan25'; document header reads 'Wine lis…_ | unknown (only one dated edition seen, Jan 2025, still c… | Trade contact form: https://adnams-plc.myshopify.com/pages/trade-contact-us (linked from https:… | ◆ |
| [Inverarity Morton](https://www.inveraritymorton.com/) | Unit 7, 1 Evanton Drive, Thornliebank Industr… | Scotland's largest independent composite drinks distributor (2,000+ wines p… | Public, unpriced | Flipsnack flipbook with direct underlying PDF (129pp) — _PDF filename MASTER2023_2023-10-23_10-02-46.pdf; pdfinfo CreationDate 2023-10-20; intro te…_ | appears annual portfolio editions (2023 master under a … | Trade contact page https://www.inveraritymorton.com/trade-contact/ (email with business details… | ◆ |
| [Ellis Wines](https://www.elliswines.co.uk/) | Hanworth, Middlesex (West London), UK — Richm… | Family on-trade merchant (est. 1822, B Corp) supplying 1,000+ wines to UK r… | Partial (web/flipbook) | Issuu flipbook (embedded; no direct PDF/xlsx download) — _Portfolio page states 'Prices in effect from 1st March 2026' and 'Last Updated: 27th March…_ | Annual portfolio (2026 edition) with interim page updat… | Customer portal https://ell-app.openimagination.co.uk/login (existing accounts); new trade acco… | ◆ |
| [Lea & Sandeman](https://www.leaandsandeman.co.uk/) | London, UK (Chelsea HQ + shops; on-trade dist… | Independent merchant (small family producers, full price spectrum) with a d… | Partial (web/flipbook) | public web catalogue with retail prices (fine-wine table has COPY/VIEW/PDF/XLS e… | unknown | Request trade list via form on https://www.leaandsandeman.co.uk/trade-sales.html or email sales… | ◆ |
| [Davy's Wine Merchants](https://www.davywine.co.uk/) | Greenwich, London, UK | Fifth-generation family merchant (est. 1870): wholesale supply to London re… | Gated |  — _Wine-bar arm hosts 'Retail-Wine-List-All-Bars-Autumn-Winter-2025.pdf' (uploaded 2025/09 on…_ | unknown (bar retail list looks seasonal from its 'Autum… | Email/phone only — merchant site davywine.co.uk is a maintenance holding page (2026-06-10) and … | ◆ |
| [Forth Wines](https://www.forthwines.com/ (301-redirects to https://www.inveraritymorton.com/)) | Formerly Crawford Place, Milnathort, Kinross … | Former Scottish drinks distributor; acquired by Inverarity Morton in 2013 a… | Gated | none (no independent site or list any more) — _Acquisition reported Oct 2013 (sltn.co.uk 2013-10-31, insider.co.uk); domain forthwines.co…_ | unknown | Via Inverarity Morton: https://www.inveraritymorton.com/trade-contact/ | ◆ |

### Fine-wine merchants & brokers (12)

| Supplier | Location | Focus | Public list? | Format & evidence | Cadence | Access | V |
|---|---|---|---|---|---|---|---|
| [Clark Foyster Wines](https://www.clarkfoysterwines.co.uk/) | 42B The Broadway, London W5 2NP | Specialist importer of Austria, France (Burgundy/Champagne/Languedoc/Roussi… | **PRICED, public** | PDF (retail-priced wine list: case price + bottle price columns) — _Filename 'Clark-Foyster-Wines-Wine-List-February-2025.pdf' (uploads/2025/01); PDF states '…_ | Roughly seasonal/annual editions implied (Feb 2025 edit… | Email sales@cfwines.co.uk - wines page CTA 'Contact us to receive a copy of our latest Wine Lis… | ◆ |
| [Farr Vintners](https://www.farrvintners.com/) | Commodore House, Battersea Reach, Juniper Dri… | Britain's largest wholesale fine wine merchant (est. 1978): top Bordeaux, e… | **PRICED, public** | XLSX / CSV / TXT / JSON full stock-list export, no login — _Fetched /winelist_csv.php?output=txt on 2026-06-10: columns incl. 'GBP Price','GBP Unit Pr…_ | continuous (exports are generated live from the stock d… | No registration needed to see the priced list; ordering via sales team (wholesale + private cli… | ◆ |
| [Flint Wines](https://www.flintwines.com/) | 16A Stannary Street, Kennington, London SE11 … | Burgundy-specialist importer (100+ domaines, absorbed Domaine Direct) with … | **PRICED, public** | xlsx (two files: full inventory list + broking list) — _Both files stated 'last updated Tuesday, 19th May 2026'; dated filenames pc-inventory-1905…_ | unknown (only one dated snapshot observed; dated-filena… | Trade account registration form at https://www.flintwines.com/new-account-form (restaurants, re… | ◆ |
| [Goedhuis Waddesdon](https://goedhuiswaddesdon.com/) | London (HQ; stock at Octavian Cellars, Wiltsh… | Fine wine merchant (Goedhuis & Co + Rothschild's Waddesdon Wine merger): Bu… | Public, unpriced | PDF brochure (11MB, downloaded and verified - no prices in text) — _Filename 'Goedhuis_Waddesdon_Commercial_Collection_2026_web.pdf' (year-dated 2026; CDN ?v=…_ | annual (inferred from year-dated brochure filename only… | Commercial portfolio page https://goedhuiswaddesdon.com/pages/goedhuis-waddesdon-commercial-por… | ◆ |
| [Raeburn Fine Wines](https://www.raeburnfinewines.com/) | 23 Comely Bank Road, Edinburgh EH4 1DS (Londo… | Trade-facing importer/distributor of classic fine wine, ~85% France & Italy… | Public, unpriced | PDF (213-215pp, Excel-generated); priced Main/Quick/agency/EP tariffs in Excel o… — _Raeburn-Wines-WEB-TARIFF-April-2026.pdf (verified live, 213pp, PDF created 1 Apr 2026) and…_ | Roughly every 1-3 months (dated editions Apr 2026, Jun … | Trade enquiry form at https://www.raeburnfinewines.com/trade/ ; PDF says 'Just tell us which [t… | ◆ |
| [Bordeaux Index](https://bordeauxindex.com/) | London, UK (also Hong Kong, Singapore) | Fine wine & spirits merchant + LiveTrade, a real-time bid/offer trading pla… | Partial (web/flipbook) | web platform (live bid/offer prices); no downloadable file; LiveTrade API availa… — _Marketplace shows live bids/offers and last-trade prices with dates without login (e.g. 20…_ | real-time (continuous live trading platform) | Free LiveTrade account registration (login at https://bordeauxindex.com/auth/login; FAQ states … | ◆ |
| [Corney & Barrow](https://www.corneyandbarrow.com/) | 1 Thomas More Street, London E1W 1YZ (also Le… | Fine wine merchant est. 1780, two Royal Warrants, ~75% exclusive agencies, … | Partial (web/flipbook) | On-trade price list as public Issuu flipbook (priced, no confirmed direct file d… — _Issuu originalPublishDate 2026-02-13 for 'ON TRADE PRICE LIST 2026/27'; earlier editions '…_ | on-trade price list issued per list-year (2020, 2022-23… | On-trade page https://www.corneyandbarrow.com/on-trade (not loadable from here); account creati… | ◆ |
| [Nekter Wines](https://www.nekterwines.com/) | Victoria Park, London E2 0NN, UK | Importer of fine, minimal-intervention wines from Australia, California and… | Partial (web/flipbook) | Wholesale page shows a static unpriced range image (Nekter_Table_Final_no_prices… | unknown | Email info@nekterwines.com — wholesale page states 'contact us on info@nekterwines.com for more… | ◆ |
| [Yapp Brothers](https://www.yapp.co.uk/) | Sparkford, Somerset BA22 7JQ (relocated from … | Rhone/Loire and French regional specialist importer, retail webshop plus tr… | Partial (web/flipbook) | web catalogue (Magento retail shop with retail prices); printed catalogue on req… | unknown | Email Tradesales@Yapp.co.uk (contact page states 'Trade enquiries - please call our office or e… | ◆ |
| [Berry Bros. & Rudd](https://www.bbr.com/) | London (63 Pall Mall / 3 St James's Street, S… | Britain's oldest fine wine & spirits merchant: fine wine retail, en primeur… | Gated | web catalogue (retail); no trade list file found — _Live 'Bordeaux 2025 En Primeur: all releases' page on bbr.com (seen 2026-06-10); no dated …_ | unknown | No public trade page on bbr.com (homepage nav/footer and contact page checked). Spirits trade a… | ◆ |
| [H2Vin](https://h2vin.co.uk/) | London (historic); business merged into Allia… | Old World specialist importer (France/Spain core, founded 2009) - acquired … | Gated | none (own site defunct); successor Alliance Wine publishes a public unpriced PDF… — _Successor PDF filename 'aw_spring-price-list-2026_a5-generic.pdf', document titled 'Spring…_ | unknown (successor portfolio labelled 'Spring 2026' sug… | Via Alliance Wine (https://www.alliancewine.com/) - homepage promotes the 'blended Alliance Win… | ◆ |
| [Justerini & Brooks (Trade)](https://www.justerinis.com/about-us/trade) | London + Edinburgh | Fine-wine merchant trade arm; 4,000+ wines, 350+ producers (Burgundy/Bordea… | Gated | None public (price list by request) — _Public-portfolio-PDF claim REFUTED 1-2 in verification_ | En primeur campaigns + ongoing | Just-Trade@justerinis.com / 020 7484 6430 (Edinburgh 0131 226 4202); price-list request form | ◆◆ |

### Specialist & natural importers (17)

| Supplier | Location | Focus | Public list? | Format & evidence | Cadence | Access | V |
|---|---|---|---|---|---|---|---|
| [Les Caves de Pyrene](https://www.lescaves.co.uk/) | Guildford, Surrey | Natural-wine pioneer importer; ~1,800+ wines | Public, unpriced | Direct PDF (406pp, stable URL, superseding editions) — _Cover 'April 2026'; Wayback shows 'November 2024' at same URL_ | Periodic superseding editions (semi-annual-ish) | Priced lists from the office (trade contact); public PDF is deliberately price-free | ◆◆ |
| [Basket Press Wines](https://www.basketpresswines.com) | London, UK (W3) | Low-intervention wines and cider from Central/Eastern Europe (Czech Republi… | Partial (web/flipbook) | web catalogue (Wix online shop, retail prices) | unknown | Email sales@basketpresswines.com (mailto link verified on homepage); no trade portal or registr… | ◆ |
| [Carte Blanche Wines](https://www.carteblanchewines.com/) | Bridges Centre, Drybridge House, Drybridge Pa… | Importer-wholesaler (founded 2009) of organic/biodynamic, terroir-focused s… | Partial (web/flipbook) | Public retail web shop (/shop, VAT-inc prices for private clients) + producer pa… — _Homepage promotes dated trade tastings: 17 February Skinners Hall and South Coast Trade Ta…_ | unknown | Email info@carteblanchewines.com (trade enquiries) / orders@carteblanchewines.com; newsletter s… | ◆ |
| [Gergovie Wines](https://gergovie-wines.com) | 70 Druid Street, Bermondsey, London SE1 2HQ (… | Natural wines (no chemicals, wild-yeast ferments) mainly from France, plus … | Partial (web/flipbook) | web catalogue (Squarespace retail shop by country) | unknown | Wholesale by email: contact page states 'For Wholesale inquiries contact us at: orders@gergovie… | ◆ |
| [Indigo Wine](https://www.indigowine.com/) | Office 306, 141-157 Acre Lane, London SW2 5UA | Independent importer of artisanal, organic/biodynamic, low-intervention win… | Partial (web/flipbook) | web catalogue (no download) | unknown | Contact form at https://www.indigowine.com/contact-us/ (described as for wine and stockist enqu… | ◆ |
| [L'Art du Vin](https://www.aduv.co.uk/) | 18 Taxi Way, Dalgety Bay, Fife KY11 9JT, Scot… | Independent importer/distributor of artisan organic and biodynamic wines (F… | Partial (web/flipbook) | Shopify web catalogue with retail prices; no downloadable trade list found | unknown | On-trade services page https://www.aduv.co.uk/pages/on-trade-services -> 'get in touch' email s… | ◆ |
| [Modal Wines](https://www.modalwines.com/) | 71 Winston Road, London N16 9LN | Farming-led importer of organic/biodynamic/regenerative low-intervention wi… | Partial (web/flipbook) | retail web shop only; trade portfolio by email request | unknown | Email info@modalwines.com to "request our latest trade portfolio or to book a tasting" (per htt… | ◆ |
| [Uncharted Wines](https://www.unchartedwines.com/) | London, UK | Keg/wine-on-tap specialist importer (largest keg range in Europe) of small … | Partial (web/flipbook) | Shopify web catalogue (retail prices); Cin7 B2B portal for trade | unknown | Cin7 B2B portal at https://unchartedwines.b2b.cin7.com/ (login only, no self-serve registration… | ◆ |
| [Vine Trail](https://www.vinetrail.co.uk/) | Temple Studios, Bristol BS1 6QA | French grower specialist (est. 1989), low-sulphur/natural-leaning wines fro… | Partial (web/flipbook) | web catalogue (unpriced, organised by region with grower/grape/ABV); latest wine… | unknown | Email enquiries@vinetrail.co.uk - orders page says 'If you would like to receive our latest win… | ◆ |
| [Wines Under the Bonnet](https://www.winesutb.com) | London, UK (shop at 161 Kirkdale, SE26 per se… | Natural wine importer: France, England, Germany, Italy, Spain, Chile (Loire… | Partial (web/flipbook) | web pages (country producer pages, no prices) + one priced web catalogue page (n… — _Public 'Bedrock Trade Catalogue 2025' page (URL slug dated 2025) showing wholesale £ price…_ | unknown | Email hello@winesutb.com (mailto verified on Bedrock catalogue page, subject 'Bedrock Wholesale… | ◆ |
| [Dynamic Vines](https://www.dynamicvines.com) | Arch 5, Discovery Estate, St James's Road, Be… | Premium biodynamic/organic European wines, 60+ exclusive producers (Emidio … | Gated | trade login portal (trade prices shown only to approved logged-in accounts); pub… | unknown | Trade account via https://www.dynamicvines.com/trade — per /trade and /trade-terms-conditions s… | ○ |
| [Graft Wine Company](https://www.graftwine.co.uk/) | 59 Charlotte Street, London W1T 4PE, UK | Independent importer-distributor (2019 merger of Red Squirrel + The Knotted… | Gated | Producer profile pages only; no wine list, prices, or downloads on site — _Legacy pages 'Portfolio 2019 Registration' and '2020 Digital Portfolio Registration' surfa…_ | unknown | Contact form at https://www.graftwine.co.uk/contact — site says 'speak to the account manager i… | ◆ |
| [Newcomer Wines](https://newcomerwines.com/) | 5 Dalston Lane, London E8 | Austria/Central-Europe specialist (~80 growers); import+distribution+retail | Gated | None public — _Old Squarespace trade catalogue 404_ | unknown | Email hello@newcomerwines.com ("open a trade account and receive our trade list") | ◆◆ |
| [Otros Vinos](https://www.otrosvinos.co.uk/) | London E9 (address seen in search snippet onl… | One-man importer of Spanish (plus some southern French) natural wines from … | Gated | none public | unknown | Email via https://www.otrosvinos.co.uk/contact-1 — page invites restaurants/bars/shops to enqui… | ◆ |
| [Swig Wines](https://swig.co.uk/) | London | Specialist importer | Gated | None public | unknown | Embedded trade contact form at https://swig.co.uk/pages/trade (no published email) | ◆◆ |
| [Tutto Wines](https://tuttowines.com/) | London (company: Tutto Wines Limited) | Natural-wine importer rooted in small Italian growers (now also France, Ger… | Gated | none public (producer profiles only) — _Journal new-release posts dated 09 June 2026, 02 June 2026, 14 May 2026, etc. (offers, not…_ | unknown | Email info@tuttowines.com to schedule a tasting or open a trade account (per https://tuttowines… | ◆ |
| [Wanderlust Wine](https://wanderlustwine.co.uk/) | 32 Blackfriars Rd, London SE1 | Sustainable/organic importer; ~62 exclusive producers (Bruno Paillard, Till… | Gated | None public (zero document links in HTML) | unknown | hello@wanderlustwine.co.uk / +44 (0)203 4885258 (no trade portal) | ◆◆ |

### Ireland / EU (3)

| Supplier | Location | Focus | Public list? | Format & evidence | Cadence | Access | V |
|---|---|---|---|---|---|---|---|
| [Cassidy Wines](https://www.cassidywines.com/) | Magna Drive, Citywest, Dublin 24, Ireland | Family-run Irish importer-distributor since 1977 supplying hotels, restaura… | Public, unpriced | pdf — _PDF confirmed live (HTTP 200, 13.8MB, 112pp): content titled '2026 PORTFOLIO', HTTP Last-M…_ | annual (yearly portfolio book: 2025-named file replaced… | Contact form at https://www.cassidywines.com/get-in-touch/; named sales-team emails printed in … | ◆ |
| [Tindal Wine Merchants](https://tindalwine.com/) | B4 Centrepoint, Rosemount Business Park, Ball… | Family-owned Irish importer (est. 2004) distributing to the trade; ~1,436-w… | Partial (web/flipbook) | web catalogue | unknown | Online account at https://tindalwine.com/register/ (existing trade account holders only); new t… | ◆ |
| [Febvre & Co](https://febvrewines.ie/) | Highfield House, Burton Hall Road, Sandyford,… | Irish importer-distributor (est. 1963, acquired by Musgrave MarketPlace 202… | Gated |  | unknown | Product Enquiry form on febvrewines.ie (used to request the wine list with pricing) or email in… | ◆ |

---

*Maintained as part of CellarOS sourcing research. Re-verify before relying on any URL —
editions supersede and links rotate. Generated from two verification workflows on 2026-06-10.*
