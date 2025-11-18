# Contributing to Sparxstar & Starmus Projects

First off, thank you for considering a contribution. Your expertise is valued, and your efforts help us build a better, more resilient platform.

This document provides a set of guidelines for contributing to this repository. These are mostly guidelines, not strict rules. Use your best judgment, and feel free to propose changes to this document in a pull request.

**Important Note:** This is a private, managed repository. Contributions are welcome from team members and invited collaborators only. We do not accept unsolicited pull requests from the general public. If you believe you have found a security vulnerability, please follow our [Security Policy](#security-vulnerability-reporting).

## Table of Contents

- [Code of Conduct](#code-of-conduct)
- [How Can I Contribute?](#how-can-i-contribute)
  - [Reporting Bugs](#reporting-bugs)
  - [Suggesting Enhancements](#suggesting-enhancements)
  - [Your First Code Contribution](#your-first-code-contribution)
- [Development Setup](#development-setup)
- [Style Guides](#style-guides)
  - [Git Commit Messages](#git-commit-messages)
  - [JavaScript Style Guide](#javascript-style-guide)
  - [PHP Style Guide](#php-style-guide)
  - [CSS/Styling Style Guide](#cssstyling-style-guide)
- [Pull Request Process](#pull-request-process)
- [Security Vulnerability Reporting](#security-vulnerability-reporting)

## Code of Conduct

This project and everyone participating in it is governed by our [Code of Conduct](CODE_OF_CONDUCT.md), [Ethics](ETHICS.md), and [Terms](TERMS.md). By participating, you are expected to uphold these policies c. Please report unacceptable behavior to the project lead.

## How Can I Contribute?

### Reporting Bugs

Bugs are tracked as GitHub issues. Before opening a new issue, please perform a quick search to see if the problem has already been reported.

When you are creating a bug report, please include as many details as possible:

- **A clear and descriptive title** for the issue.
- **A detailed description of the problem.**
- **Steps to reproduce the behavior.** Be as specific as possible.
- **Expected behavior vs. actual behavior.**
- **Screenshots or screen recordings** are extremely helpful.
- **Environment details:**
  - OS and version
  - Browser and version
  - Device type (if applicable)
  - WordPress version
  - Active plugins that might be relevant

### Suggesting Enhancements

Enhancement suggestions are tracked as GitHub issues.

- **Use a clear and descriptive title.**
- **Provide a step-by-step description of the suggested enhancement** in as many details as possible.
- **Explain why this enhancement would be useful.** What problem does it solve?
- **Provide examples of how it would work.** Code snippets or mockups are welcome.

### Your First Code Contribution

Unsure where to begin? You can start by looking through `good-first-issue` and `help-wanted` issues.

Before you start working on a feature or bug, **please communicate your intention** by commenting on the relevant issue or creating a new one. This prevents duplicated effort and allows for early architectural feedback.

## Development Setup

1.  **Fork & Clone:** Fork the repository to your own GitHub account and clone it to your local machine.
2.  **Branch:** Create a new feature branch from the `main` or `develop` branch. Branch names should be descriptive, using prefixes like `feature/`, `bugfix/`, or `refactor/`.
    ```bash
    git checkout -b feature/my-new-feature-name
    ```
3.  **Install Dependencies:** This project uses `pnpm` as its package manager.
    ```bash
    pnpm install
    ```
4.  **Build Assets:** Run the build command to compile JavaScript and CSS.
    ```bash
    pnpm build
    ```
5.  **Linting:** Before committing, ensure your code adheres to our standards by running the linter.
    ```bash
    pnpm lint
    ```
    To automatically fix issues, you can run:
    ```bash
    pnpm format
    ```

## Style Guides

### Git Commit Messages

- Use the present tense ("Add feature" not "Added feature").
- Use the imperative mood ("Move file to..." not "Moves file to...").
- Limit the first line to 72 characters or less.
- Reference issues and pull requests liberally in the commit body.

Example:

```
feat: Add chunked TUS upload strategy

Implements the resilient, chunked TUS uploader in the Core module.
This allows for the upload of large files (>5MB) without crashing the
browser tab, which is critical for handling external music files.
The upload strategy now correctly falls back to this method based on
the file size and environment profile.

Fixes #42
```

### JavaScript Style Guide

All JavaScript must adhere to the configuration in our `.eslintrc.js` file.
- Use ES Modules (`import`/`export`) for all new JavaScript.
- Avoid global scope pollution.
- Write clear, self-documenting code. Add JSDoc comments for all public functions and complex logic.

### PHP Style Guide

All PHP code must adhere to the [WordPress PHP Coding Standards](https://developer.wordpress.org/coding-standards/wordpress-coding-standards/php/).
- Use strict types (`declare(strict_types=1);`) where possible.
- Use namespaces for all classes.

### CSS/Styling Style Guide

- All CSS must adhere to the configuration in our `.stylelintrc.js` file.
- Use BEM (Block, Element, Modifier) naming conventions for CSS classes (e.g., `.starmus-recorder__button--primary`).

## Pull Request Process

1.  **Ensure all tests and linting checks pass** before submitting your PR.
2.  **Update the `README.md` and any relevant documentation** with details of changes to the interface, new environment variables, etc.
3.  **Create a Pull Request** against the `main` or `develop` branch of the main repository.
4.  **Provide a clear title and description** for your PR, explaining the "why" and "what" of your changes. Link to the issue(s) your PR resolves.
5.  **Request a Review:** Tag the project lead or relevant team members for review.
6.  **Respond to Feedback:** The reviewer may ask for changes. Please be open to discussion and make the required updates. Once your PR is approved, it will be merged by a maintainer.

## Security Vulnerability Reporting

If you discover a security vulnerability, please **DO NOT** open a public issue. Instead, send a private email to `security@sparxstar.com` (or your designated security contact).

We take security seriously and will respond promptly.

