-- PostgreSQL HR Management System Schema
-- Filename: hr_management_system.sql
-- Description: Complete database schema for HR operations including employee management,
--              attendance tracking, leave management, payroll, performance reviews,
--              training, and recruitment

-- Create extensions
CREATE EXTENSION IF NOT EXISTS "uuid-ossp";
CREATE EXTENSION IF NOT EXISTS "pgcrypto";

-- Create schemas
CREATE SCHEMA IF NOT EXISTS hr;
CREATE SCHEMA IF NOT EXISTS auth;

-- Set search path
SET search_path TO hr, auth, public;

-- Create auth tables
CREATE TABLE auth.users
(
    id            UUID PRIMARY KEY         DEFAULT uuid_generate_v4(),
    email         VARCHAR(255) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    first_name    VARCHAR(100),
    last_name     VARCHAR(100),
    is_active     BOOLEAN                  DEFAULT TRUE,
    created_at    TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    updated_at    TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE auth.roles
(
    id          SERIAL PRIMARY KEY,
    name        VARCHAR(50) NOT NULL UNIQUE,
    description TEXT,
    created_at  TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE auth.user_roles
(
    user_id UUID REFERENCES auth.users (id) ON DELETE CASCADE,
    role_id INTEGER REFERENCES auth.roles (id) ON DELETE CASCADE,
    PRIMARY KEY (user_id, role_id)
);

-- Create HR tables
CREATE TABLE hr.departments
(
    id          SERIAL PRIMARY KEY,
    name        VARCHAR(100) NOT NULL,
    code        VARCHAR(20) UNIQUE,
    description TEXT,
    parent_id   INTEGER REFERENCES hr.departments (id),
    active      BOOLEAN                  DEFAULT TRUE,
    created_at  TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE hr.positions
(
    id            SERIAL PRIMARY KEY,
    title         VARCHAR(100) NOT NULL,
    department_id INTEGER REFERENCES hr.departments (id),
    salary_grade  INTEGER,
    description   TEXT,
    active        BOOLEAN                  DEFAULT TRUE,
    created_at    TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    updated_at    TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE hr.employees
(
    id                      UUID PRIMARY KEY         DEFAULT uuid_generate_v4(),
    employee_id             VARCHAR(20) UNIQUE,
    user_id                 UUID REFERENCES auth.users (id),
    first_name              VARCHAR(100) NOT NULL,
    last_name               VARCHAR(100) NOT NULL,
    middle_name             VARCHAR(100),
    email                   VARCHAR(255) NOT NULL UNIQUE,
    phone                   VARCHAR(20),
    hire_date               DATE         NOT NULL,
    position_id             INTEGER REFERENCES hr.positions (id),
    department_id           INTEGER REFERENCES hr.departments (id),
    manager_id              UUID REFERENCES hr.employees (id),
    birth_date              DATE,
    address                 TEXT,
    city                    VARCHAR(100),
    state                   VARCHAR(100),
    postal_code             VARCHAR(20),
    country                 VARCHAR(100),
    emergency_contact_name  VARCHAR(200),
    emergency_contact_phone VARCHAR(20),
    status                  VARCHAR(20)              DEFAULT 'ACTIVE',
    created_at              TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    updated_at              TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE hr.attendance
(
    id            SERIAL PRIMARY KEY,
    employee_id   UUID REFERENCES hr.employees (id) ON DELETE CASCADE,
    date          DATE NOT NULL,
    clock_in      TIMESTAMP WITH TIME ZONE,
    clock_out     TIMESTAMP WITH TIME ZONE,
    break_minutes INTEGER                  DEFAULT 0,
    notes         TEXT,
    status        VARCHAR(20)              DEFAULT 'PRESENT',
    created_at    TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    updated_at    TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT unique_employee_date UNIQUE (employee_id, date)
);

CREATE TABLE hr.leave_types
(
    id          SERIAL PRIMARY KEY,
    name        VARCHAR(100) NOT NULL UNIQUE,
    description TEXT,
    paid        BOOLEAN                  DEFAULT TRUE,
    active      BOOLEAN                  DEFAULT TRUE,
    created_at  TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE hr.leave_balances
(
    id            SERIAL PRIMARY KEY,
    employee_id   UUID REFERENCES hr.employees (id) ON DELETE CASCADE,
    leave_type_id INTEGER REFERENCES hr.leave_types (id) ON DELETE CASCADE,
    year          INTEGER NOT NULL,
    total_days    DECIMAL(5, 1) NOT NULL,
    used_days     DECIMAL(5, 1) DEFAULT 0,
    created_at    TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    updated_at    TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT unique_employee_leave_type_year UNIQUE (employee_id, leave_type_id, year)
);

CREATE TABLE hr.leave_requests
(
    id            SERIAL PRIMARY KEY,
    employee_id   UUID REFERENCES hr.employees (id) ON DELETE CASCADE,
    leave_type_id INTEGER REFERENCES hr.leave_types (id),
    start_date    DATE NOT NULL,
    end_date      DATE NOT NULL,
    half_day      BOOLEAN DEFAULT FALSE,
    reason        TEXT,
    status        VARCHAR(20) DEFAULT 'PENDING',
    approved_by   UUID REFERENCES hr.employees (id),
    approved_at   TIMESTAMP WITH TIME ZONE,
    rejected_by   UUID REFERENCES hr.employees (id),
    rejected_at   TIMESTAMP WITH TIME ZONE,
    notes         TEXT,
    created_at    TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    updated_at    TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT start_before_end CHECK (start_date <= end_date)
);

CREATE TABLE hr.salary_components
(
    id           SERIAL PRIMARY KEY,
    name         VARCHAR(100) NOT NULL UNIQUE,
    description  TEXT,
    component_type VARCHAR(20) NOT NULL, -- 'EARNING', 'DEDUCTION', 'BENEFIT'
    taxable      BOOLEAN DEFAULT TRUE,
    active       BOOLEAN DEFAULT TRUE,
    created_at   TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    updated_at   TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE hr.salary_structures
(
    id           SERIAL PRIMARY KEY,
    name         VARCHAR(100) NOT NULL UNIQUE,
    description  TEXT,
    effective_from DATE NOT NULL,
    effective_to DATE,
    active       BOOLEAN DEFAULT TRUE,
    created_at   TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    updated_at   TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE hr.salary_structure_components
(
    id                  SERIAL PRIMARY KEY,
    salary_structure_id INTEGER REFERENCES hr.salary_structures (id) ON DELETE CASCADE,
    component_id        INTEGER REFERENCES hr.salary_components (id) ON DELETE CASCADE,
    amount              DECIMAL(12, 2),
    percentage          DECIMAL(5, 2),
    formula             TEXT,
    created_at          TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    updated_at          TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT unique_structure_component UNIQUE (salary_structure_id, component_id)
);

CREATE TABLE hr.employee_salaries
(
    id                  SERIAL PRIMARY KEY,
    employee_id         UUID REFERENCES hr.employees (id) ON DELETE CASCADE,
    salary_structure_id INTEGER REFERENCES hr.salary_structures (id),
    base_salary         DECIMAL(12, 2) NOT NULL,
    effective_from      DATE NOT NULL,
    effective_to        DATE,
    currency            VARCHAR(3) DEFAULT 'USD',
    payment_interval    VARCHAR(20) DEFAULT 'MONTHLY', -- 'WEEKLY', 'BIWEEKLY', 'MONTHLY', etc.
    active              BOOLEAN DEFAULT TRUE,
    created_at          TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    updated_at          TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE hr.payroll_periods
(
    id           SERIAL PRIMARY KEY,
    period_name  VARCHAR(100) NOT NULL,
    start_date   DATE NOT NULL,
    end_date     DATE NOT NULL,
    payment_date DATE NOT NULL,
    status       VARCHAR(20) DEFAULT 'PENDING', -- 'PENDING', 'PROCESSING', 'COMPLETED', 'CANCELED'
    created_at   TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    updated_at   TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT start_before_end CHECK (start_date <= end_date),
    CONSTRAINT end_before_payment CHECK (end_date <= payment_date)
);

CREATE TABLE hr.payslips
(
    id                SERIAL PRIMARY KEY,
    employee_id       UUID REFERENCES hr.employees (id) ON DELETE CASCADE,
    payroll_period_id INTEGER REFERENCES hr.payroll_periods (id),
    gross_pay         DECIMAL(12, 2) NOT NULL,
    total_deductions  DECIMAL(12, 2) NOT NULL,
    net_pay           DECIMAL(12, 2) NOT NULL,
    payment_method    VARCHAR(50),
    payment_reference VARCHAR(100),
    status            VARCHAR(20) DEFAULT 'DRAFT', -- 'DRAFT', 'APPROVED', 'PAID'
    approved_by       UUID REFERENCES hr.employees (id),
    approved_at       TIMESTAMP WITH TIME ZONE,
    notes             TEXT,
    created_at        TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    updated_at        TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE hr.payslip_details
(
    id              SERIAL PRIMARY KEY,
    payslip_id      INTEGER REFERENCES hr.payslips (id) ON DELETE CASCADE,
    component_id    INTEGER REFERENCES hr.salary_components (id),
    component_name  VARCHAR(100) NOT NULL,
    amount          DECIMAL(12, 2) NOT NULL,
    component_type  VARCHAR(20) NOT NULL, -- 'EARNING', 'DEDUCTION', 'BENEFIT'
    created_at      TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE hr.performance_review_cycles
(
    id           SERIAL PRIMARY KEY,
    name         VARCHAR(100) NOT NULL,
    start_date   DATE NOT NULL,
    end_date     DATE NOT NULL,
    status       VARCHAR(20) DEFAULT 'PENDING', -- 'PENDING', 'ACTIVE', 'COMPLETED', 'CANCELED'
    description  TEXT,
    created_at   TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    updated_at   TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT start_before_end CHECK (start_date <= end_date)
);

CREATE TABLE hr.performance_reviews
(
    id                      SERIAL PRIMARY KEY,
    review_cycle_id         INTEGER REFERENCES hr.performance_review_cycles (id),
    employee_id             UUID REFERENCES hr.employees (id) ON DELETE CASCADE,
    reviewer_id             UUID REFERENCES hr.employees (id),
    self_assessment         TEXT,
    manager_assessment      TEXT,
    goals_achieved          TEXT,
    areas_of_improvement    TEXT,
    rating                  DECIMAL(3, 2), -- Scale (e.g., 1.0 to 5.0)
    status                  VARCHAR(20) DEFAULT 'PENDING', -- 'PENDING', 'SELF_ASSESSMENT', 'MANAGER_REVIEW', 'COMPLETED'
    submission_date         DATE,
    completion_date         DATE,
    created_at              TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    updated_at              TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE hr.training_programs
(
    id              SERIAL PRIMARY KEY,
    name            VARCHAR(200) NOT NULL,
    description     TEXT,
    start_date      DATE,
    end_date        DATE,
    location        VARCHAR(200),
    max_participants INTEGER,
    trainer         VARCHAR(200),
    status          VARCHAR(20) DEFAULT 'UPCOMING', -- 'UPCOMING', 'ONGOING', 'COMPLETED', 'CANCELED'
    cost            DECIMAL(10, 2),
    created_at      TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE hr.employee_trainings
(
    id                 SERIAL PRIMARY KEY,
    employee_id        UUID REFERENCES hr.employees (id) ON DELETE CASCADE,
    training_program_id INTEGER REFERENCES hr.training_programs (id) ON DELETE CASCADE,
    registration_date  DATE NOT NULL,
    status             VARCHAR(20) DEFAULT 'REGISTERED', -- 'REGISTERED', 'ATTENDING', 'COMPLETED', 'DROPPED'
    completion_date    DATE,
    certification      VARCHAR(200),
    score              DECIMAL(5, 2),
    feedback           TEXT,
    created_at         TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    updated_at         TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE hr.documents
(
    id            SERIAL PRIMARY KEY,
    employee_id   UUID REFERENCES hr.employees (id) ON DELETE CASCADE,
    document_type VARCHAR(100) NOT NULL,
    document_name VARCHAR(255) NOT NULL,
    file_path     VARCHAR(500) NOT NULL,
    file_size     INTEGER,
    mime_type     VARCHAR(100),
    upload_date   DATE NOT NULL,
    expiry_date   DATE,
    notes         TEXT,
    created_at    TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    updated_at    TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE hr.job_openings
(
    id                  SERIAL PRIMARY KEY,
    position_id         INTEGER REFERENCES hr.positions (id),
    department_id       INTEGER REFERENCES hr.departments (id),
    job_title           VARCHAR(200) NOT NULL,
    job_description     TEXT,
    requirements        TEXT,
    responsibilities    TEXT,
    vacancy_count       INTEGER DEFAULT 1,
    status              VARCHAR(20) DEFAULT 'OPEN', -- 'DRAFT', 'OPEN', 'CLOSED', 'CANCELED'
    posting_date        DATE,
    closing_date        DATE,
    min_salary          DECIMAL(12, 2),
    max_salary          DECIMAL(12, 2),
    employment_type     VARCHAR(50), -- 'FULL_TIME', 'PART_TIME', 'CONTRACT', etc.
    work_location       VARCHAR(200),
    remote_work         BOOLEAN DEFAULT FALSE,
    created_by          UUID REFERENCES hr.employees (id),
    created_at          TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    updated_at          TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE hr.applicants
(
    id            SERIAL PRIMARY KEY,
    first_name    VARCHAR(100) NOT NULL,
    last_name     VARCHAR(100) NOT NULL,
    email         VARCHAR(255) NOT NULL UNIQUE,
    phone         VARCHAR(20),
    resume_path   VARCHAR(500),
    cover_letter  TEXT,
    source        VARCHAR(100), -- Where they heard about the job
    status        VARCHAR(20) DEFAULT 'NEW', -- 'NEW', 'SCREENING', 'INTERVIEWING', 'OFFERED', 'HIRED', 'REJECTED'
    notes         TEXT,
    created_at    TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    updated_at    TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE hr.job_applications
(
    id            SERIAL PRIMARY KEY,
    job_opening_id INTEGER REFERENCES hr.job_openings (id) ON DELETE CASCADE,
    applicant_id  INTEGER REFERENCES hr.applicants (id) ON DELETE CASCADE,
    application_date DATE NOT NULL,
    status        VARCHAR(20) DEFAULT 'SUBMITTED', -- 'SUBMITTED', 'REVIEWING', 'SHORTLISTED', 'REJECTED', 'HIRED'
    notes         TEXT,
    created_at    TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    updated_at    TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT unique_applicant_job UNIQUE (job_opening_id, applicant_id)
);

CREATE TABLE hr.interview_rounds
(
    id               SERIAL PRIMARY KEY,
    job_opening_id   INTEGER REFERENCES hr.job_openings (id),
    round_name       VARCHAR(100) NOT NULL,
    round_order      INTEGER NOT NULL,
    description      TEXT,
    created_at       TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    updated_at       TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT unique_round_order UNIQUE (job_opening_id, round_order)
);

CREATE TABLE hr.interviews
(
    id                SERIAL PRIMARY KEY,
    job_application_id INTEGER REFERENCES hr.job_applications (id) ON DELETE CASCADE,
    interview_round_id INTEGER REFERENCES hr.interview_rounds (id),
    scheduled_date    TIMESTAMP WITH TIME ZONE NOT NULL,
    location          VARCHAR(200),
    is_virtual        BOOLEAN DEFAULT FALSE,
    virtual_meeting_link VARCHAR(500),
    status            VARCHAR(20) DEFAULT 'SCHEDULED', -- 'SCHEDULED', 'COMPLETED', 'RESCHEDULED', 'CANCELED'
    feedback          TEXT,
    rating            INTEGER,
    interviewed_by    UUID REFERENCES hr.employees (id),
    created_at        TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    updated_at        TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

-- Create audit logging table
CREATE TABLE hr.audit_logs
(
    id           SERIAL PRIMARY KEY,
    table_name   VARCHAR(100) NOT NULL,
    record_id    VARCHAR(100) NOT NULL,
    action       VARCHAR(20) NOT NULL, -- 'INSERT', 'UPDATE', 'DELETE'
    old_values   JSONB,
    new_values   JSONB,
    performed_by UUID REFERENCES auth.users (id),
    ip_address   VARCHAR(50),
    user_agent   VARCHAR(500),
    created_at   TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

-- Create indexes for better performance
CREATE INDEX idx_employees_department ON hr.employees(department_id);
CREATE INDEX idx_employees_position ON hr.employees(position_id);
CREATE INDEX idx_employees_manager ON hr.employees(manager_id);
CREATE INDEX idx_leave_requests_employee ON hr.leave_requests(employee_id);
CREATE INDEX idx_leave_requests_status ON hr.leave_requests(status);
CREATE INDEX idx_attendance_employee_date ON hr.attendance(employee_id, date);
CREATE INDEX idx_payslips_employee ON hr.payslips(employee_id);
CREATE INDEX idx_payslips_period ON hr.payslips(payroll_period_id);
CREATE INDEX idx_job_applications_job ON hr.job_applications(job_opening_id);
CREATE INDEX idx_job_applications_applicant ON hr.job_applications(applicant_id);
CREATE INDEX idx_interviews_application ON hr.interviews(job_application_id);

-- Add initial roles
INSERT INTO auth.roles (name, description) VALUES
                                               ('ADMIN', 'System administrator with full access'),
                                               ('HR_MANAGER', 'HR manager with access to all HR functions'),
                                               ('HR_SPECIALIST', 'HR specialist with limited HR access'),
                                               ('MANAGER', 'Department manager with access to their team data'),
                                               ('EMPLOYEE', 'Regular employee with access to their own data');

-- Add common leave types
INSERT INTO hr.leave_types (name, description, paid) VALUES
                                                         ('Annual Leave', 'Regular vacation time', TRUE),
                                                         ('Sick Leave', 'Leave due to illness or medical appointments', TRUE),
                                                         ('Maternity Leave', 'Leave for childbirth and childcare for mothers', TRUE),
                                                         ('Paternity Leave', 'Leave for childcare for fathers', TRUE),
                                                         ('Bereavement Leave', 'Leave due to death of family member', TRUE),
                                                         ('Unpaid Leave', 'Leave without pay', FALSE),
                                                         ('Compensatory Off', 'Leave in lieu of overtime work', TRUE);

-- Add function to update timestamps on record updates
CREATE OR REPLACE FUNCTION update_timestamp()
RETURNS TRIGGER AS $$
BEGIN
    NEW.updated_at = CURRENT_TIMESTAMP;
RETURN NEW;
END;
$$ LANGUAGE plpgsql;

-- Create triggers to automatically update timestamps
DO $$
DECLARE
t text;
BEGIN
FOR t IN
SELECT table_name
FROM information_schema.tables
WHERE table_schema IN ('hr', 'auth')
  AND table_type = 'BASE TABLE'
    LOOP
        EXECUTE format('CREATE TRIGGER update_timestamp
                       BEFORE UPDATE ON %I
                       FOR EACH ROW
                       EXECUTE FUNCTION update_timestamp()', t);
END LOOP;
END
$$ LANGUAGE plpgsql;

-- End of HR Management System Schema
