# Artran (Credit Note) Amount Deduction

## Overview

When displaying **INV (Invoice)** type artrans, the amounts are automatically adjusted by deducting the amounts from linked **CN (Credit Note)** type artrans.

## Implementation Details

### Model Level (`Artran` Model)

The `Artran` model includes the following methods and accessors:

1. **`getTotalCreditNotesAmount()`** - Calculates the total amount of all linked credit notes for an INV invoice
2. **`getNetBilAdjustedAttribute()`** - Returns adjusted NET_BIL (original NET_BIL minus linked credit notes)
3. **`getGrandBilAdjustedAttribute()`** - Returns adjusted GRAND_BIL (original GRAND_BIL minus linked credit notes)
4. **`getGrossBilAdjustedAttribute()`** - Returns adjusted GROSS_BIL (original GROSS_BIL minus linked credit notes)
5. **`getTax1BilAdjustedAttribute()`** - Returns proportionally adjusted TAX1_BIL based on credit note ratio

### Controller Level

#### InvoiceController

- **`index()`** - Lists invoices with adjusted amounts for INV type
- **`show()`** - Shows single invoice with adjusted amounts for INV type
- **`adjustInvoiceAmounts()`** - Private helper method that adjusts invoice amounts by deducting linked credit notes

#### CustomerInvoiceController

- **`getOutstandingInvoices()`** - Returns customer outstanding invoices with adjusted amounts for INV type

## How It Works

1. **For INV type invoices:**
   - The system queries all linked credit notes via the `artrans_credit_note` pivot table
   - Sums the `NET_BIL` of all linked credit notes
   - Deducts this total from the invoice's `NET_BIL`, `GRAND_BIL`, and `GROSS_BIL`
   - Proportionally adjusts `TAX1_BIL` based on the credit note ratio

2. **For CN/CR type artrans:**
   - No adjustment is made (these are the credit notes themselves)

3. **For other types:**
   - No adjustment is made

## Example

**Original Invoice (INV):**
- NET_BIL: 1000.00
- GRAND_BIL: 1060.00 (includes 6% tax)
- GROSS_BIL: 1000.00
- TAX1_BIL: 60.00

**Linked Credit Note (CN):**
- NET_BIL: 200.00

**Adjusted Invoice Display:**
- NET_BIL: 800.00 (1000 - 200)
- GRAND_BIL: 848.00 (1060 - 200)
- GROSS_BIL: 800.00 (1000 - 200)
- TAX1_BIL: 48.00 (60 * (1 - 200/1060) = 60 * 0.8113)

## Database Structure

The relationship between invoices and credit notes is stored in the `artrans_credit_note` table:
- `invoice_id` - References `artrans.artrans_id` (the INV invoice)
- `credit_note_id` - References `artrans.artrans_id` (the CN credit note)

## Important Notes

1. **Original values are preserved** - The adjustment only affects the displayed values in API responses. The original database values remain unchanged.

2. **Only INV type is adjusted** - Other artran types (CN, CR, CS, CB, etc.) are not affected by this logic.

3. **Tax adjustment is proportional** - The tax amount is adjusted proportionally based on the ratio of credit notes to the original grand total.

4. **Minimum value is 0** - Adjusted amounts will never go below 0 (using `max(0, ...)`).

5. **Multiple credit notes** - If an invoice has multiple linked credit notes, all their amounts are summed before deduction.

## API Response Fields

When displaying INV invoices, the following fields are adjusted:
- `NET_BIL` - Adjusted net amount
- `GRAND_BIL` - Adjusted grand total
- `GROSS_BIL` - Adjusted gross amount
- `TAX1_BIL` - Proportionally adjusted tax

Additional fields available:
- `total_credit_notes_amount` - Total amount of all linked credit notes
- `net_bil_adjusted` - Same as NET_BIL for INV (for consistency)
- `grand_bil_adjusted` - Same as GRAND_BIL for INV (for consistency)
- `gross_bil_adjusted` - Same as GROSS_BIL for INV (for consistency)
- `tax1_bil_adjusted` - Same as TAX1_BIL for INV (for consistency)

## Date

This feature was implemented on: 2025-01-XX

