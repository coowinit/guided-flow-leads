# Guided Flow Leads

**Guided Flow Leads** is a WordPress plugin for building guided, step-by-step lead capture experiences with a chat-style interface.

It helps businesses collect leads, guide visitors through predefined flows, and store both lead entries and session progress for follow-up and analysis.

## Features

- Step-based guided flow builder
- Choice, text, and input style steps
- Option branching with **Next Step ID**
- Floating chat-style frontend launcher
- Lead data storage
- Session tracking and resume support
- Admin flow editor
- REST API endpoints for flow start, next, resume, and restart
- WordPress-friendly plugin structure
- GitHub Actions release packaging

## Use Cases

Guided Flow Leads is suitable for:

- product recommendation flows
- sales qualification flows
- contact and quote pre-screening
- guided lead capture forms
- conversational landing pages
- lightweight chatbot-style business flows

## How It Works

1. Create steps in the **Flow Editor**
2. Define the step type and content
3. Add options for choice-based steps
4. Set a **Next Step ID** to control the path
5. Display the guided flow on the frontend
6. Store leads and sessions in WordPress database tables

## Admin Overview

The plugin includes:

- **Flow Editor** for building step logic
- **Settings** for plugin configuration
- **Usage Guide** for onboarding and internal documentation
- lead and session related data storage for future expansion

## Data Storage

The plugin currently uses dedicated database tables for lead and session data, including:

- lead entries
- session progress

This structure is designed to support guided conversations and resumable user journeys.

## Development Status

This project is under active development.

Current direction includes:

- improving flow editor UX
- expanding lead/session management
- refining frontend interaction patterns
- improving packaging and release workflow

## Installation

1. Download the latest plugin zip from **Releases**
2. In WordPress admin, go to **Plugins → Add New → Upload Plugin**
3. Upload the zip file
4. Activate the plugin
5. Open **Guided Flow Leads** in the WordPress admin menu

## Release Workflow

This repository uses GitHub Actions to:

- build installable plugin zip packages
- attach them to tagged GitHub Releases

Recommended release flow:

1. Update code locally
2. Commit changes
3. Push to GitHub
4. Create a new tag such as `v1.0.8`
5. Push the tag
6. Let GitHub Actions generate the release package

## Roadmap

Planned improvements may include:

- improved flow analytics
- richer lead management UI
- shortcode/embed documentation
- onboarding enhancements
- FAQ and documentation improvements
- more flexible frontend rendering

## Contributing

This repository is currently maintained as an active custom plugin project.  
Suggestions, improvements, and structured refactoring ideas are welcome.

## License

Please add your preferred license information here.