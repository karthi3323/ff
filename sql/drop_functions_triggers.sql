-- Drop triggers first
DROP TRIGGER IF EXISTS update_companies_updated_at ON ff_sch.companies;
DROP TRIGGER IF EXISTS update_users_updated_at ON ff_sch.users;
DROP TRIGGER IF EXISTS update_parties_updated_at ON ff_sch.parties;
DROP TRIGGER IF EXISTS update_products_updated_at ON ff_sch.products;

DROP TRIGGER IF EXISTS audit_invoices ON ff_sch.invoices;
DROP TRIGGER IF EXISTS audit_parties ON ff_sch.parties;
DROP TRIGGER IF EXISTS audit_products ON ff_sch.products;
DROP TRIGGER IF EXISTS audit_invoice_items ON ff_sch.invoice_items;

-- Drop functions
DROP FUNCTION IF EXISTS ff_sch.update_updated_at_column();
DROP FUNCTION IF EXISTS ff_sch.audit_trigger();
DROP FUNCTION IF EXISTS ff_sch.set_user_context(INTEGER);