-- Table pour les dépenses des projets
CREATE TABLE IF NOT EXISTS project_expenses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    project_id INT NOT NULL,
    expense_date DATE NOT NULL,
    category ENUM('personnel', 'equipment', 'materials', 'services', 'travel', 'other') NOT NULL,
    description TEXT NOT NULL,
    amount DECIMAL(15,2) NOT NULL,
    invoice_number VARCHAR(100),
    supplier VARCHAR(255),
    payment_status ENUM('pending', 'paid', 'cancelled') NOT NULL DEFAULT 'pending',
    payment_date DATE,
    payment_method ENUM('cash', 'check', 'transfer', 'other'),
    receipt_url VARCHAR(255),
    notes TEXT,
    created_by INT NOT NULL,
    approved_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id),
    FOREIGN KEY (approved_by) REFERENCES users(id),
    INDEX idx_project (project_id),
    INDEX idx_date (expense_date),
    INDEX idx_category (category),
    INDEX idx_status (payment_status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table pour les factures
CREATE TABLE IF NOT EXISTS invoices (
    id INT AUTO_INCREMENT PRIMARY KEY,
    project_id INT NOT NULL,
    invoice_number VARCHAR(100) NOT NULL UNIQUE,
    invoice_date DATE NOT NULL,
    due_date DATE NOT NULL,
    supplier VARCHAR(255) NOT NULL,
    description TEXT,
    subtotal DECIMAL(15,2) NOT NULL,
    tax_amount DECIMAL(15,2) DEFAULT 0,
    total_amount DECIMAL(15,2) NOT NULL,
    status ENUM('draft', 'sent', 'paid', 'overdue', 'cancelled') NOT NULL DEFAULT 'draft',
    payment_date DATE,
    payment_reference VARCHAR(100),
    document_url VARCHAR(255),
    notes TEXT,
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id),
    INDEX idx_project (project_id),
    INDEX idx_number (invoice_number),
    INDEX idx_status (status),
    INDEX idx_dates (invoice_date, due_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Vue pour le rapport financier des projets
CREATE OR REPLACE VIEW project_financial_summary AS
SELECT 
    p.id as project_id,
    p.title as project_title,
    p.budget_estimated,
    p.budget_validated,
    COALESCE(SUM(pe.amount), 0) as total_expenses,
    p.budget_validated - COALESCE(SUM(pe.amount), 0) as remaining_budget,
    ROUND((COALESCE(SUM(pe.amount), 0) / NULLIF(p.budget_validated, 0)) * 100, 2) as budget_consumed_percent,
    COUNT(DISTINCT CASE WHEN pe.payment_status = 'pending' THEN pe.id END) as pending_payments,
    COUNT(DISTINCT i.id) as invoice_count,
    COALESCE(SUM(CASE WHEN i.status = 'paid' THEN i.total_amount ELSE 0 END), 0) as paid_invoices_total
FROM projects p
LEFT JOIN project_expenses pe ON p.id = pe.project_id
LEFT JOIN invoices i ON p.id = i.project_id
WHERE p.archived = FALSE
GROUP BY p.id;
