# Distributed Smart Traffic Monitoring & Enforcement System (DSTMES)

![Version](https://img.shields.io/badge/version-1.0.0-blue.svg)
![License](https://img.shields.io/badge/license-MIT-green.svg)
![Platform](https://img.shields.io/badge/platform-Web-orange.svg)

---

## 🌟 Overview

The **Distributed Smart Traffic Monitoring & Enforcement System (DSTMES)** is a modern, scalable solution designed to automate traffic monitoring and enforcement operations. The system follows a **distributed client–server architecture**, enabling real-time traffic surveillance, violation detection, and centralized administrative control.

DSTMES emphasizes **performance, scalability, and professional UI/UX**, utilizing glassmorphism design principles and smooth micro-animations to deliver a premium-grade interface suitable for traffic authorities and enforcement agencies.

---

## 🏗️ System Architecture

DSTMES is built using a **Distributed Architecture**, ensuring modularity, fault tolerance, and scalability.

### Architecture Components

- **Frontend (CLIENT):**
  A responsive web application developed with **HTML5, Vanilla CSS3, and ES6 JavaScript**.  
  Communicates with the backend through RESTful APIs.

- **Backend (SERVER):**
  A **PHP-based REST API** responsible for business logic, authentication, validation, and database operations.

- **Data Layer:**
  **MySQL / MariaDB** relational database optimized for integrity, consistency, and fast traffic data retrieval.

### Distributed System Features

- **API-Centric Design:**  
  The client and server are fully decoupled and can be deployed independently.

- **SyncManager Module:**  
  Handles real-time data synchronization between the user interface and the central database.

- **CORS Security:**  
  Controlled cross-origin access enabling secure multi-server and distributed deployments.

---

## 🚀 Core Modules

### 📹 Surveillance & Live Monitoring
- Distributed camera deployment across multiple geographical **nodes**
- Live traffic stream visualization with low latency
- Real-time traffic flow and congestion analytics

### 🚗 Vehicle & Owner Management
- Digital vehicle registry with **Ethiopian plate number validation**
- Centralized vehicle owner profile management
- Complete ownership history and status tracking

### 🚨 Enforcement & Violations
- Automated and manual traffic violation logging
- Accident recording with severity classification
- Watchlist system for stolen, flagged, or suspicious vehicles

### 💳 Financials & Notifications
- Automated fine calculation based on violation type
- Payment tracking and fine management system
- Real-time notification system for vehicle owners

---

## 🛠️ Technology Stack

- **Frontend:** HTML5, CSS3 (Custom Glassmorphism Design System), JavaScript (ES6+)
- **Backend:** PHP 7.4+ (RESTful API)
- **Database:** MySQL / MariaDB
- **Tools & Utilities:** XAMPP, Fetch API, SyncManager Pattern

---

## 📁 Project Structure

```text
├── CLIENT/                     # Frontend Web Application
│   ├── css/                    # Design System & Styling
│   ├── js/                     # Application Logic & API Integration
│   ├── auth/                   # Authentication Pages
│   ├── owners/                 # Owner Dashboard & Features
│   └── *.html                  # System Pages (Vehicles, Accidents, etc.)
│
├── SERVER/                     # Backend API Layer
│   ├── api/                    # REST API Endpoints
│   ├── db.php                  # Database Configuration
│   └── uploads/                # Media & Evidence Storage
│
└── PROJECT_DESCRIPTION.md      # Detailed Project Documentation
