=== PMT Quotations & Invoices ===
Requires at least: 6.0
Requires PHP: 7.4
Stable tag: 1.6.5
License: GPLv2 or later

Quotations, proforma invoices, tax invoices and delivery notes for PMT Asia, with saved customers, a WooCommerce product picker, custom items with images, payment tracking and a customisable template.

== Install ==
1. Plugins > Add New > Upload Plugin, choose the zip, activate.
2. Open Quotations > Settings and check your business details, bank accounts, tax and numbering.
3. Quotations > New quotation, or "Create quotation" on any request in Bulk RFQs.

== Notes ==
* Save as PDF: open the document link and use "Print or save as PDF".
* Prices are not converted between currencies. Store prices are only filled in when the quote currency matches the store currency.

== Changelog ==
= 1.6.5 =
* New "Product images" section at the end of the document: every item with a picture, shown large in rows of three, numbered like the item table, with name and SKU. Pages break between rows, never through a picture.
* On by default for quotations and proforma invoices, off for tax invoices and delivery notes. Switch it per type in Settings > Show / hide, or per document under "Show on this document". The heading can be renamed under Words used on the document.
* The footer note now sits at the very end of the document.

= 1.6.4 =
* Separate Mobile number for your business (Settings > Business) and for each customer, printed as its own "Mob." line under "Ph.".
* The original combined phone line is split automatically: Ph. +94372096118, Mob. +9607686927 | +94704149823.

= 1.6.3 =
* The header repeated on pages 2 onward no longer shows the customer name.

= 1.6.2 =
* Tighter business and customer details: the label column is only as wide as its longest label and lines sit closer together.
* Document summary reads "Quotation No.", "Quotation date" and so on (editable under Words used on the document). Quotations also show Paid and Owing.
* Every page repeats the full header with the logo at the same size as page 1.
* The two top columns can no longer push the summary past the page edge on devices with wider fonts.

= 1.6.1 =
* Every series counts on its own from its own saved documents: the next number is the highest saved number of that type plus one (QT-0002 after QT-0001, TXI-0002 after TXI-0001). Other types, and old PMT- or INV- numbers, never affect it.
* A number is only used when a document is saved. "Create tax invoice", "Create delivery note", "Duplicate" and so on now open an unsaved draft; nothing is stored until you click Save, and the original is marked converted at that moment.
* Deleting the last document of a series frees its number again.
* Settings > Numbers and dates: "Next number" is now "Start at" (the first number of a series) and the table shows the next new number for each type.

= 1.6.0 =
* New numbering, one series per type, each starting at 0001: QT-0001 (quotation), PI-0001 (proforma invoice), TXI-0001 (tax invoice), DN-0001 (delivery note). Existing documents keep their numbers; numbers already used are always skipped.
* Separate pages for each type under Quotes & Invoices: Quotations, Proforma invoices, Tax invoices and Delivery notes. Each has its own title, status cards with totals, status filter and "New ..." button. All documents is still there.
* The editor's back link returns to the type's own page.

= 1.5.0 =
* Four document types in one template: Quotation, Proforma invoice, Tax invoice and Delivery note (DN- numbers). Each shows its own details: valid until, due date, payments, or delivery method, vehicle and driver.
* Show / hide: Settings > Show / hide sets what each type prints by default; every document can switch any part on or off in its own "Show on this document" box.
* Extra details: add any label and value (Project, Sales person...) to a document.
* Tax per currency, applied automatically: GST 8% for USD and VAT 18% for LKR by default (Settings > Currency and tax).
* Discount section: whole-document discount as a percentage or an amount, with its own label; each item can also take a percentage or an amount off.
* Amount in words on proformas and tax invoices.
* Convert along the chain: quotation to proforma, tax invoice or delivery note; proforma to tax invoice (advance payments carry over, proforma marked Invoiced); tax invoice to delivery note.
* Signature lines per type, including "Delivered by" and "Received in good condition by" on delivery notes.
* Terms blocks choose which document types they start on.
* Bigger default logo (90px, up to 160px). Money never wraps in the item table. Empty image column hidden automatically.
* Shop managers can now save settings.
* Existing settings and documents are upgraded automatically; nothing is lost.

= 1.4.0 =
* Minimal, monochrome document look: black on white, light grey label bars, plain "Label: value" rows for business, customer and summary, a grey table heading, and a double underline under the final amount. Colour only where you choose it (accent colour is charcoal by default).
* Arial is the new default typeface (Settings > Template > Typeface).
* Highlighted terms are now grey with a dark edge instead of red.
* Summary box shows number, dates, reference, total and, on invoices, paid and balance due.
* New editable labels for the row names (Address, Ph., Email, Attn., Web and the summary heading).

= 1.3.0 =
* New professional document layout: business details and document title in the header, a facts box with the amount due, filled table heading and total band.
* Always A4. Long documents are laid onto A4 pages: the full header on page 1, a compact header on every later page, the item table heading repeated, the last item kept with the totals, and every page numbered "Page 2 / 5". Printed PDFs match the screen exactly.
* Notes, bank details and signature lines are never split across pages.
* Removed the paper-size option (A4 only). Added an "Amount due" line to invoices.
* Fixed item images in the sample preview.

= 1.2.0 =
* Changing a document's currency only changes the symbol. Prices are never converted. The new symbol shows beside every price field (items, delivery fee, discount, payments, store picker) and in all totals.
* Removed exchange rates and the conversion step added in 1.1.0.

= 1.1.0 =
* Price tools: raise or lower all prices by a percentage, same discount on every line, rounding, with undo.
* Store picker: set quantity and price when adding a product.
* Per-currency decimal places.
* Customers can have a preferred currency, used for new quotations.

= 1.0.0 =
* First release.
