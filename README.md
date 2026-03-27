# Guided Flow Leads

A WordPress plugin for building guided lead capture flows with step-based logic, session tracking, and a floating chat-style UI.

---

## Overview

**Guided Flow Leads** helps you create interactive, step-by-step flows that guide visitors through questions, choices, and input fields, then store lead and session data inside WordPress.

It is designed for businesses that want a lightweight guided selling, lead qualification, or conversational form experience without building a full custom application.

---

## Core Features

- Step-based flow builder in WordPress admin
- Support for guided choice and input steps
- Option branching with **Next Step ID**
- Floating frontend chat-style launcher
- Lead data collection and storage
- Session tracking and resume support
- REST API endpoints for flow actions
- Admin-side usage guide
- GitHub Actions release packaging

---

## Typical Use Cases

Guided Flow Leads can be used for:

- product recommendation flows
- quote pre-qualification
- sales lead capture
- conversational inquiry forms
- guided support funnels
- landing page conversion flows

---

## How It Works

### 1. Build the flow
Create steps in the **Flow Editor** and define how users move from one step to the next.

### 2. Add options
For choice-based steps, define:

- **Label** — what the user sees
- **Value** — what gets stored
- **Next Step ID** — where the flow goes next

### 3. Launch the flow
Display the guided experience on the frontend through the plugin’s UI integration.

### 4. Store lead and session data
The plugin stores both lead information and session progress for follow-up and future expansion.

---

## Admin Areas

The plugin currently includes:

- **Flow Editor**
- **Settings**
- **Usage Guide**

These areas help site admins configure flows, understand setup, and manage future workflow expansion.

---

## Data Structure

The plugin uses dedicated database tables for:

- lead entries
- session progress

This makes it possible to support resumable guided flows and structured lead capture.

---

## Installation

1. Download the latest plugin package from **Releases**
2. Go to **WordPress Admin → Plugins → Add New → Upload Plugin**
3. Upload the zip file
4. Activate the plugin
5. Open **Guided Flow Leads** from the WordPress admin menu

---

## Release Workflow

This repository uses GitHub Actions to automatically build installable plugin zip packages for tagged releases.

### Recommended release process

1. Update code locally
2. Commit changes
3. Push to GitHub
4. Create a new tag such as `v1.1.0`
5. Push the tag
6. GitHub Actions builds the plugin package and attaches it to the Release

---

## Development Status

This project is actively being refined.

Recent work includes:

- improving flow editor behavior
- fixing option row rendering
- adding a professional admin usage guide
- stabilizing GitHub Actions packaging and release flow

---

## Roadmap

Planned improvements may include:

- better lead management UI
- improved session inspection tools
- richer frontend rendering controls
- shortcode/embed documentation
- onboarding improvements
- FAQ and help documentation

---

## Who This Plugin Is For

This plugin is a good fit for:

- WordPress developers
- B2B business websites
- lead-generation landing pages
- quote and inquiry systems
- guided product selection experiences

---

## Contributing

This repository is being maintained as an active custom WordPress plugin project.  
Structured suggestions, bug fixes, and UX improvements are welcome.

---

## License

Add your preferred license information here.