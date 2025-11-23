# 💼 Deal or No Deal: High-Stakes Negotiation

> **CSC 4370/6370 Web Programming (Project 6)** > A server-side web application replicating the high-stakes TV game show using **PHP, HTML, and CSS only.**

![Deal or No Deal Banner](images/intro.jpeg)

## 👥 Team Members
* **Shriya Kotala** - Team Leader (UI/UX Design, QA, Coordination)
* **Sreenija Kanugonda** -(Backend Architecture, Algorithms, Volatility Engine)

---

## 📖 Project Overview
The objective of this project was to engineer a fully interactive, state-aware game application without using **Client-Side JavaScript**.

To achieve this, we treated the web browser as a passive rendering engine and the server as a **State Machine**. Every interaction—from picking a briefcase to accepting a deal—triggers a `POST` request that updates the PHP Session state and re-renders the view.

### 🛠️ Technical Stack
* **Backend:** PHP 8.x (Session Management, Game Logic)
* **Frontend:** HTML5, CSS3 (Grid Layout, Flexbox)
* **Animations:** CSS3 Keyframes (Triggered via PHP class injection)
* **Database/Storage:** `$_SESSION` (No external DB required)

---

## ✨ Key Features

### 🟢 Core Gameplay
1.  **Session-Based State Machine:** The application persists game data (Opened Cases, Current Value, Offer History) across stateless HTTP requests.
2.  **CSS-Only Animations:** Logic in `index.php` detects which case was just opened and injects an `.animate-open` class to trigger a CSS 3D flip effect on page load.
3.  **Responsive Design:** A 3-column layout featuring the "Player Case," the "Game Board," and "Progressive Money Boards."

### 🎓 Graduate Extensions (Advanced Features)
We implemented all four required graduate-level features:

#### 1. 📉 Volatile Market Engine
Every turn carries a **15% probability** of a random market event that mathematically alters the values inside the unopened briefcases:
* **Market Crash:** High values (> $50k) are **HALVED**.
* **Market Boom:** Low values (< $1k) are **DOUBLED**.
* **Inflation:** All values increase by **15%**.
* **Tax:** All values decrease by **10%**.

#### 2. 🧠 Strategic Banker AI
The Banker offers are not random. The algorithm:
* Calculates the **Expected Value (EV)** of the remaining board.
* **Bluffs (Lowball):** Offers ~60% of EV if the board is "Risky" (many high values left).
* **Pressures (Highball):** Offers ~90% of EV if the board is "Safe" to tempt the player to quit.

#### 3. 📝 Progressive Value Revelation
The sideboards act as a "Smart Ledger."
* They track the **Original Value** vs. the **Current Value**.
* If a value changes due to volatility, it is displayed as: ~~$100,000~~ **$50,000**.
* When a case is opened, the logic correctly identifies and grays out the value, even if it has drifted due to inflation/taxes.

#### 4. ⏱️ Dynamic Round Structure
The game does not follow a fixed "6, 5, 4..." round structure. The pacing adjusts dynamically based on **Risk**:
* **High Risk Board:** The player is forced to open **MORE** cases (5) before a Banker offer.
* **Safe Board:** The player opens **FEWER** cases (3-4), allowing for more frequent negotiations.

---

## 🚀 Installation & Setup

This project requires a PHP-enabled server (Apache/Nginx).

### Option 1: Local Development (MAMP/XAMPP)
1.  Download and install **MAMP** (Mac) or **XAMPP** (Windows).
2.  Navigate to the `htdocs` folder:
    * Mac: `/Applications/MAMP/htdocs/`
    * Windows: `C:\xampp\htdocs\`
3.  Create a folder named `deal`.
4.  Paste the project files (`index.php`, `functions.php`, `style.css`, `images/`) into this folder.
5.  Start the Servers in MAMP/XAMPP.
6.  Open your browser and go to: `http://localhost/deal/` (or `http://localhost:8888/deal/` for MAMP default).

### Option 2: CODD Server
1.  Connect via FileZilla using your campus credentials.
2.  Upload the project folder to `public_html`.
3.  Access via your student URL.
