# GST Presentation Boundary

Invoice templates display CRM invoice-item GST snapshots: taxable value, tax rate, CGST, SGST, IGST, cess, HSN/SAC, and tax treatment. They do not invoke the GST calculator from Blade and do not persist altered values.

The renderer groups rows by HSN/SAC, stored rate, and stored tax treatment. Cess remains separate. Interstate totals omit zero CGST and SGST rows; the GST summary displays empty components as a dash. Zero-rated, exempt, non-GST, and reverse-charge treatments remain labelled from their stored snapshots.

The output is a presentation layer, not GST return filing, e-invoicing, e-way bill, or tax advice.
