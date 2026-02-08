# Modèle Logique de Données (MLD) - Freelance Lucid

## Important Note

This logical data model was manually refined from an auto-generated **Mocodo** schema to ensure:

- Doctrine ORM compatibility  
- Relational database best practices  
- Strict SaaS multi-tenant data isolation  

---

## Overview

The **Freelance Lucid** data model is composed of four core entities:

- **User**
- **Client**
- **Invoice**
- **InvoiceItem**

Each resource is owned by a single **User**, ensuring strict data isolation in a single-database, multi-tenant architecture.

---

## Entities

### User

| Attribute     | Notes |
|---------------|-------|
| idUser        | Primary key; integer; auto-increment |
| email         | Unique; string |
| password      | Hashed password; string |
| roles         | JSON or array |
| firstName     | String |
| lastName      | String |
| companyName   | String |
| address       | Text |
| postCode      | String |
| city          | String |
| country       | String |
| siretNumber   | Unique; optional; string |
| vatNumber     | String |
| phoneNumber   | String |
| createdAt     | DateTime |
| updatedAt     | DateTime; nullable |

---

### Client

| Attribute     | Notes |
|---------------|-------|
| idClient      | Primary key; integer; auto-increment |
| lastName      | String |
| firstName     | String |
| companyName   | String |
| email         | String |
| address       | String |
| phoneNumber   | String |
| siret         | String |
| vatNumber     | String |
| city          | String |
| postCode      | String |
| country       | String (default: France) |
| createdAt     | DateTime |
| updatedAt     | DateTime; nullable |
| idUser        | Foreign key → User.idUser |

---

### Invoice

| Attribute                   | Notes |
|-----------------------------|-------|
| idInvoice                   | Primary key; integer; auto-increment |
| invoiceNumber               | String; indexed |
| projectTitle                | String; indexed |
| dateCreated                 | DateTime |
| dueDate                     | DateTime; indexed |
| status                      | String; indexed; default `DRAFT` |
| sentAt                      | DateTime; nullable |
| paidAt                      | DateTime; nullable |
| totalHt                     | Decimal(10,2) |
| totalVat                    | Decimal(10,2) |
| totalAmount                 | Decimal(10,2) |
| currency                    | String; default `EUR` |
| updatedAt                   | DateTime; nullable |
| frozenClientName            | String; nullable |
| frozenClientAddress         | String; nullable |
| frozenClientPostcode        | String; nullable |
| frozenClientCity            | String; nullable |
| frozenClientCountry         | String; nullable |
| frozenClientPhone           | String; nullable |
| frozenClientEmail           | String; nullable |
| frozenClientSiret           | String; nullable |
| frozenClientVat             | String; nullable |
| frozenClientCompanyName     | String; nullable |
| idClient                    | Foreign key → Client.idClient |
| idUser                      | Foreign key → User.idUser |

---

### InvoiceItem

| Attribute       | Notes |
|-----------------|-------|
| idInvoiceItem   | Primary key; integer; auto-increment |
| description     | String |
| quantity        | Decimal(10,2) |
| unitPrice       | Decimal(10,2) |
| vatRate         | Decimal(5,2) |
| vatAmount       | Decimal(10,2); nullable |
| totalHt         | Decimal(10,2); nullable |
| totalTtc        | Decimal(10,2); nullable |
| idInvoice       | Foreign key → Invoice.idInvoice |

---

## Relationships

- **User** owns **Clients**
- **User** issues **Invoices**
- **Client** has **Invoices**
- **Invoice** contains **InvoiceItems**

All relationships are **one-to-many**.

---

## Indexes and Constraints

- **Unique**
  - `User.email`
  - `User.siretNumber` (optional)

- **Indexed**
  - `invoiceNumber`
  - `projectTitle`
  - `status`
  - `dateCreated`
  - `dueDate`

- Monetary fields use `DECIMAL(10,2)`
- Roles stored as JSON or array
- All foreign keys enforce referential integrity

---

## Security and Multi-Tenant Isolation

- Every **Client**, **Invoice**, and **InvoiceItem** is directly or indirectly linked to a **User**
- Symfony **Voters** enforce ownership rules
- Single-database, multi-tenant SaaS architecture
- Audit fields (`createdAt`, `updatedAt`) included on all entities

---

## Example Access Control Rules

- **Client read**  
  Allowed if `client.idUser === currentUser.id`

- **Invoice read**  
  Allowed if:
  - `invoice.idUser === currentUser.id`  
  **OR**
  - `invoice.client.idUser === currentUser.id`

- **InvoiceItem modify**  
  Allowed if `invoiceItem.invoice.idUser === currentUser.id`
---
