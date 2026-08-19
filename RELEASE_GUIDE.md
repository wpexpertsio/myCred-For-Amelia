# WordPress.org Plugin Release Guide — myCred Amelia

Yeh document **myCred Amelia** plugin ko Git branch flow aur GitHub Actions ke zariye WordPress.org par release karne ka muqammal tareeqa batata hai.

---

## 💡 Aapke Sawalon ke Jawab (Important)

### 1. WordPress.org SVN mein `trunk` aur `tags` ka kya chakkar hai?
Aap ne bilkul sahi poocha! WordPress.org SVN mein do main folders hote hain:
- **`trunk/`**: Isme hamesha latest/current code rehta hai.
- **`tags/{version}/`**: Isme specific version ka release Snapshot hota hai (e.g. `tags/2.1.0/`).

**Humari GitHub Pipeline isko kaise handle karti hai?**
Aapko manual SVN commands chalane ki zaroorat nahi hai. Humara `10up/action-wordpress-plugin-deploy` action automatic:
1. Aapka latest code WordPress.org SVN ke **`trunk/`** folder mein Sync karta hai.
2. Usi code ki copy SVN ke **`tags/{version}/`** folder mein create kar deta hai.
3. `.wordpress-org/` assets ko SVN ke `assets/` folder mein push karta hai.

Is tarah **ek hi action se SVN trunk, SVN tag, aur SVN assets sab ek saath update ho jaate hain!**

---

### 2. Locked `main` Branch & `development` Flow
`main` branch locked rahegi taake koi direct code push na kar sake. Development workflow yeh hoga:

```
[Feature Branch] ──(PR)──> [development] ──(PR)──> [main] ──(Git Tag)──> [WordPress.org SVN]
```

1. Developer apna kaam `feature/...` branch par karega.
2. `development` branch ke liye Pull Request (PR) kholega.
3. **PR Quality Check Pipeline** automatic chalegi (PHPCS + Version check).
4. Review ke baad code `development` mein merge hoga.
5. Jab release ka waqt aaye, `development` se `main` branch ke liye PR banegi.
6. `main` mein merge hone ke baad `main` branch se Git Tag (e.g. `2.1.0`) banega jo deployment trigger karega.

---

## ⚙️ Step 0: GitHub Setup

### 1. Repository Secrets Add Karein
GitHub Repo → **Settings** → **Secrets and variables** → **Actions**:
- `SVN_USERNAME` : WordPress.org SVN username
- `SVN_PASSWORD` : WordPress.org SVN password

### 2. `main` Branch Lock (Branch Protection Rule)
1. Repo → **Settings** → **Branches**.
2. **Add branch ruleset** ya **Add rule** par click karein.
3. Branch pattern: `main`.
4. Enable karein: **Require a pull request before merging** aur **Require status checks to pass before merging**.

---

## 🚀 Complete Step-by-Step Release Flow

### Step 1: Feature Work & PR to `development`
1. Nayi branch banayein:
   ```bash
   git checkout development
   git pull origin development
   git checkout -b feature/my-feature
   ```
2. Coding ke baad commit aur push karein:
   ```bash
   git add .
   git commit -m "Add new feature"
   git push -u origin feature/my-feature
   ```
3. GitHub par `development` branch ke against **Pull Request** kholein. Pipeline automatic PHPCS check karegi.

---

### Step 2: Prepare Release Version in `development`
Release se pehle `development` branch par:
1. `mycred-amelia.php` mein `Version: 2.1.0` Update karein.
2. `readme.txt` mein `Stable tag: 2.1.0` aur `Changelog` update karein.
3. Commit karke push karein.

---

### Step 3: Merge `development` into `main` via PR
1. GitHub par `development` se `main` branch ki taraf PR kholein.
2. PR Quality Checks pass hone par Senior / Lead PR approve aur merge karega.

---

### Step 4: Create Tag on `main` (Release Trigger)

Local par `main` pull karein aur Tag banayein:

```bash
git checkout main
git pull origin main

# Tag banayein (matches Stable tag in readme.txt)
git tag 2.1.0

# Tag push karein
git push origin 2.1.0
```

---

### Step 5: Automatic SVN Deployment
Tag push hote hi GitHub Actions automatically chalega:
- SVN `trunk` ko update karega.
- SVN `tags/2.1.0` create karega.
- `.wordpress-org` folder se banners/icons sync karega.
- WordPress.org par aapka version **Live** ho jayega!
