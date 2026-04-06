# Documentation Checklist: [PACKAGE_NAME]

Package: `phpnomad/[package]`
Status: Not Started | In Progress | Needs Review | Complete
Last Updated: YYYY-MM-DD

---

## Phase 1: Research

### Package Components
(List all interfaces, traits, classes, and exceptions found in the source code)

**Interfaces:**
- [ ] `InterfaceName` - Brief description

**Traits:**
- [ ] `TraitName` - Brief description

**Classes:**
- [ ] `ClassName` - Brief description

**Exceptions:**
- [ ] `ExceptionName` - Brief description

### Dependencies
- Depends on: (list packages this requires)
- Depended on by: (list packages that require this)

### Existing References
(List existing docs that mention this package - these need cross-reference updates)
- [ ] `path/to/doc.md` - Context of mention

---

## Phase 2: Planned Structure

(Define all files to create before writing any content)

```
docs/packages/[package]/
├── introduction.md
├── [subdirectory]/
│   ├── introduction.md
│   └── [component].md
└── [concept].md
```

### Files to Create
- [ ] `introduction.md` - Package overview
- [ ] (add all planned files here)

---

## Phase 3: Writing Checklist

### Structure
- [ ] Has `introduction.md` as main entry point
- [ ] Has subdirectories for major concepts (if applicable)
- [ ] Each interface/trait/class has dedicated documentation file

### Frontmatter (Required for ALL .md files)
- [ ] Has `id` field
- [ ] Has `slug` field
- [ ] Has `title` field
- [ ] Has `doc_type` (explanation, tutorial, reference, how-to)
- [ ] Has `summary` (1-2 sentence description)
- [ ] Has `llm_summary` (detailed description for AI consumption)
- [ ] Has `questions_answered` list
- [ ] Has `audience` list
- [ ] Has `tags` and `llm_tags`
- [ ] Has `keywords`
- [ ] Has `related` links
- [ ] Has `see_also` links

### Content - Introduction File
- [ ] Explains what the package does
- [ ] Explains why it exists / what problem it solves
- [ ] Has "Key ideas at a glance" summary
- [ ] Has installation instructions
- [ ] Has visual diagram (ASCII) showing data/control flow (if applicable)
- [ ] Lists core components with brief descriptions and links to detail pages
- [ ] Has "When to use this package" section
- [ ] Has "Relationship to other packages" section
- [ ] Has "Next steps" with links to deeper docs

### Content - Component Files
- [ ] Each interface has its own documentation file
- [ ] Each trait has its own documentation file
- [ ] Each significant class has its own documentation file
- [ ] Method signatures with parameter descriptions
- [ ] Return types explained
- [ ] Exceptions documented
- [ ] Usage examples per component

### Content - Conceptual
- [ ] Explains the patterns/architecture used
- [ ] Explains the lifecycle of operations (if applicable)
- [ ] Has "Why use this" section with benefits
- [ ] Has "Best practices" section
- [ ] Has "When NOT to use" guidance (if applicable)

### Content - Examples
- [ ] Has basic usage example
- [ ] Has real-world/practical example
- [ ] Has integration example with other packages
- [ ] Code examples are complete and runnable
- [ ] Examples show common patterns

---

## Phase 4: Review

### Cross-References
- [ ] Links to packages this depends on
- [ ] Links to packages that depend on this
- [ ] Links to related concepts in core-concepts/
- [ ] Internal links between doc files work
- [ ] **Updated existing docs with links back to this package**

### Quality
- [ ] No placeholder text
- [ ] Consistent formatting with other package docs
- [ ] Spell-checked
- [ ] Code examples tested/verified

### Final Approval
- [ ] **Approved by human** - Documentation reviewed and approved by a human

---

## Notes

(Add any package-specific notes, questions, or issues here)
