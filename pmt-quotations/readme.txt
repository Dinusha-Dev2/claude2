=== PMT Quotations & Invoices ===
Requires at least: 6.0
Requires PHP: 7.4
Stable tag: 1.5.0
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
