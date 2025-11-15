# GitHub Setup Guide

This guide will help you push the Formtura plugin to GitHub.

## ✅ What's Already Done

- ✅ Git repository initialized
- ✅ Initial commit created (72 files, 21,827 lines)
- ✅ README.md added with comprehensive documentation
- ✅ LICENSE file added (GPL-2.0)
- ✅ .gitignore configured properly
- ✅ Branch renamed to `main`
- ✅ Git user configured (adrian200065)

## 📋 Current Status

```bash
Branch: main
Commits: 2
Files tracked: 74
Ready to push: Yes
```

## 🚀 Next Steps

### 1. Create GitHub Repository

1. Go to [GitHub](https://github.com)
2. Click the **"+"** icon → **"New repository"**
3. Fill in the details:
   - **Repository name**: `formtura`
   - **Description**: "Modern WordPress Form Builder with Drag-and-Drop Interface"
   - **Visibility**: Choose Public or Private
   - **DO NOT** initialize with README, .gitignore, or license (we already have these)
4. Click **"Create repository"**

### 2. Add Remote and Push

After creating the repository, GitHub will show you commands. Use these:

```bash
# Add GitHub as remote origin
git remote add origin https://github.com/yourusername/formtura.git

# Push to GitHub
git push -u origin main
```

Replace `yourusername` with your actual GitHub username.

### 3. Verify Upload

1. Refresh your GitHub repository page
2. You should see all 74 files
3. README.md will be displayed automatically

## 🔐 Authentication

If prompted for credentials, you have two options:

### Option A: Personal Access Token (Recommended)

1. Go to GitHub → Settings → Developer settings → Personal access tokens
2. Generate new token (classic)
3. Select scopes: `repo` (full control)
4. Copy the token
5. Use it as your password when pushing

### Option B: SSH Key

1. Generate SSH key:
   ```bash
   ssh-keygen -t ed25519 -C "adrian200065@gmail.com"
   ```

2. Add to GitHub:
   ```bash
   cat ~/.ssh/id_ed25519.pub
   ```
   Copy output and add to GitHub → Settings → SSH Keys

3. Change remote to SSH:
   ```bash
   git remote set-url origin git@github.com:yourusername/formtura.git
   ```

## 📝 Repository Settings (Optional)

After pushing, configure your repository:

### Topics/Tags
Add these topics to help others find your plugin:
- `wordpress`
- `wordpress-plugin`
- `form-builder`
- `react`
- `drag-and-drop`
- `php`
- `vite`

### About Section
- **Description**: "Modern WordPress Form Builder with Drag-and-Drop Interface"
- **Website**: Your website URL (if any)
- **Topics**: Add the tags above

### Branch Protection
Protect the `main` branch:
1. Settings → Branches → Add rule
2. Branch name pattern: `main`
3. Enable:
   - ✅ Require pull request reviews
   - ✅ Require status checks to pass

## 🏷️ Creating Releases

When ready to release a version:

```bash
# Tag the current commit
git tag -a v1.0.5 -m "Release v1.0.5: Grid layout improvements"

# Push tags to GitHub
git push origin --tags
```

Then create a release on GitHub:
1. Go to Releases → Draft a new release
2. Choose the tag (v1.0.5)
3. Add release notes
4. Attach built assets (optional)
5. Publish release

## 📊 GitHub Actions (Optional)

Create `.github/workflows/build.yml` for automated builds:

```yaml
name: Build and Test

on:
  push:
    branches: [ main ]
  pull_request:
    branches: [ main ]

jobs:
  build:
    runs-on: ubuntu-latest

    steps:
    - uses: actions/checkout@v3

    - name: Setup Node.js
      uses: actions/setup-node@v3
      with:
        node-version: '18'

    - name: Install dependencies
      run: npm ci

    - name: Build
      run: npm run build

    - name: Lint
      run: npm run lint
```

## 🔄 Daily Workflow

After initial setup, your daily workflow will be:

```bash
# Make changes to files
# ...

# Stage changes
git add .

# Commit with descriptive message
git commit -m "feat: add new feature"

# Push to GitHub
git push
```

## 📚 Commit Message Convention

Use [Conventional Commits](https://www.conventionalcommits.org/):

- `feat:` - New feature
- `fix:` - Bug fix
- `docs:` - Documentation changes
- `style:` - Code style changes (formatting)
- `refactor:` - Code refactoring
- `test:` - Adding tests
- `chore:` - Maintenance tasks

Examples:
```bash
git commit -m "feat: add email field validation"
git commit -m "fix: resolve grid layout issue on mobile"
git commit -m "docs: update installation instructions"
```

## 🌿 Branching Strategy

For larger features, use feature branches:

```bash
# Create feature branch
git checkout -b feature/new-field-type

# Make changes and commit
git add .
git commit -m "feat: add file upload field"

# Push feature branch
git push -u origin feature/new-field-type

# Create Pull Request on GitHub
# After review, merge to main
```

## 📦 What's Included in Repository

### Source Code (74 files)
- ✅ All PHP source files
- ✅ React components (JSX)
- ✅ CSS stylesheets
- ✅ Configuration files
- ✅ Documentation

### Excluded (via .gitignore)
- ❌ `node_modules/` - npm dependencies
- ❌ `vendor/` - Composer dependencies
- ❌ Built assets (`builder.js`, `builder.css`)
- ❌ IDE files
- ❌ Log files

### Why Exclude Built Assets?

Built assets are excluded because:
1. They're generated from source
2. They change on every build
3. They bloat the repository
4. Users should build them locally

## 🎯 Repository Structure

```
formtura/
├── .git/                    # Git repository data
├── .gitignore              # Ignored files
├── LICENSE                 # GPL-2.0 license
├── README.md               # Main documentation
├── GITHUB_SETUP.md         # This file
├── assets/                 # Compiled assets (excluded)
├── builder/                # React source
├── doc/                    # Documentation
├── src/                    # PHP source
├── templates/              # PHP templates
├── composer.json           # PHP dependencies
├── package.json            # Node dependencies
└── vite.config.js          # Build configuration
```

## 🔍 Verifying Your Setup

Check your git status:

```bash
# View current status
git status

# View commit history
git log --oneline

# View remote configuration
git remote -v

# View branch information
git branch -a
```

## 🆘 Troubleshooting

### Issue: "Permission denied (publickey)"
**Solution**: Set up SSH key or use HTTPS with Personal Access Token

### Issue: "Remote origin already exists"
**Solution**:
```bash
git remote remove origin
git remote add origin https://github.com/yourusername/formtura.git
```

### Issue: "Failed to push some refs"
**Solution**: Pull first, then push:
```bash
git pull origin main --rebase
git push origin main
```

## 📞 Need Help?

- **Git Documentation**: https://git-scm.com/doc
- **GitHub Guides**: https://guides.github.com/
- **GitHub Support**: https://support.github.com/

## ✅ Checklist

Before pushing to GitHub:

- [x] Git repository initialized
- [x] Initial commit created
- [x] README.md added
- [x] LICENSE file added
- [x] .gitignore configured
- [ ] GitHub repository created
- [ ] Remote origin added
- [ ] Code pushed to GitHub
- [ ] Repository settings configured
- [ ] Topics/tags added

---

**Ready to push!** Follow the steps above to get your code on GitHub. 🚀
