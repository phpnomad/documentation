# Documentation Checklist: Mutator

Package: `phpnomad/mutator`
Status: Needs Review
Last Updated: 2026-01-25

---

## Phase 1: Research

### Package Components

**Interfaces:**
- [x] `Mutator` - Core interface for mutation operations
- [x] `MutatorHandler` - Handler interface for processing mutations
- [x] `MutationAdapter` - Adapter for converting between formats during mutation
- [x] `MutationStrategy` - Strategy interface for mutation execution
- [x] `HasMutations` - Interface for classes that declare mutation bindings

**Traits:**
- [x] `CanMutateFromAdapter` - Default implementation for adapter-based mutation

**Classes:**
- (none)

**Exceptions:**
- (none)

### Dependencies
- Depends on: Nothing (zero dependencies)
- Depended on by: loader, wordpress-plugin

### Existing References
- **ISOLATED** - No other documented packages currently reference the mutator package
- This package needs backlinks added when dependent packages are documented

---

## Phase 2: Planned Structure

```
docs/packages/mutator/
├── introduction.md           # Package overview
├── interfaces/
│   ├── introduction.md       # Interfaces overview
│   ├── mutator.md            # Mutator interface
│   ├── mutator-handler.md    # MutatorHandler interface
│   ├── mutation-adapter.md   # MutationAdapter interface
│   ├── mutation-strategy.md  # MutationStrategy interface
│   └── has-mutations.md      # HasMutations interface
└── traits/
    ├── introduction.md       # Traits overview
    └── can-mutate-from-adapter.md  # CanMutateFromAdapter trait
```

### Files to Create
- [x] `introduction.md` - Package overview (links to component pages)
- [x] `interfaces/introduction.md` - Interfaces overview
- [x] `interfaces/mutator.md` - Mutator interface detail
- [x] `interfaces/mutator-handler.md` - MutatorHandler interface detail
- [x] `interfaces/mutation-adapter.md` - MutationAdapter interface detail
- [x] `interfaces/mutation-strategy.md` - MutationStrategy interface detail
- [x] `interfaces/has-mutations.md` - HasMutations interface detail
- [x] `traits/introduction.md` - Traits overview
- [x] `traits/can-mutate-from-adapter.md` - CanMutateFromAdapter trait detail

**Restructured:** 2026-01-25 - Split into 9 files following file-per-interface rule.

---

## Phase 3: Writing Checklist

### Structure
- [x] Has `introduction.md` as main entry point
- [x] Has subdirectories for major concepts (if applicable)
- [x] Each interface/trait/class has dedicated documentation file

### Frontmatter (Required for ALL .md files)
- [x] Has `id` field
- [x] Has `slug` field
- [x] Has `title` field
- [x] Has `doc_type` (explanation, tutorial, reference, how-to)
- [x] Has `summary` (1-2 sentence description)
- [x] Has `llm_summary` (detailed description for AI consumption)
- [x] Has `questions_answered` list
- [x] Has `audience` list
- [x] Has `tags` and `llm_tags`
- [x] Has `keywords`
- [x] Has `related` links
- [x] Has `see_also` links

### Content - Introduction File
- [x] Explains what the package does
- [x] Explains why it exists / what problem it solves
- [x] Has "Key ideas at a glance" summary
- [x] Has installation instructions
- [x] Has visual diagram (ASCII) showing data/control flow (if applicable)
- [x] Lists core components with brief descriptions and links to detail pages
- [x] Has "When to use this package" section
- [x] Has "Relationship to other packages" section
- [x] Has "Next steps" with links to deeper docs

### Content - Component Files
- [x] Each interface has its own documentation file
- [x] Each trait has its own documentation file
- [x] Each significant class has its own documentation file - N/A, no classes
- [x] Method signatures with parameter descriptions
- [x] Return types explained
- [x] Exceptions documented - N/A, no exceptions in interfaces
- [x] Usage examples per component

### Content - Conceptual
- [x] Explains the patterns/architecture used
- [x] Explains the lifecycle of operations (if applicable)
- [x] Has "Why use this" section with benefits
- [x] Has "Best practices" section
- [x] Has "When NOT to use" guidance (if applicable)

### Content - Examples
- [x] Has basic usage example
- [x] Has real-world/practical example
- [x] Has integration example with other packages
- [x] Code examples are complete and runnable
- [x] Examples show common patterns

---

## Phase 4: Review

### Cross-References
- [x] Links to packages this depends on - N/A, no dependencies
- [x] Links to packages that depend on this
- [x] Links to related concepts in core-concepts/
- [x] Internal links between doc files work - Single file currently
- [ ] **Updated existing docs with links back to this package** - No docs reference mutator yet

### Quality
- [x] No placeholder text
- [x] Consistent formatting with other package docs
- [x] Spell-checked
- [ ] Code examples tested/verified

### Final Approval
- [ ] **Approved by human** - Documentation reviewed and approved by a human

---

## Notes

- This package has 5 interfaces and 1 trait - currently all documented in introduction
- Comprehensive workflow diagram showing adapter -> mutator -> adapter flow
- Includes testing section showing how to test mutators and adapters independently
- Dependent packages: loader, wordpress-plugin
- **RESTRUCTURED**: Split into 9 files on 2026-01-25
- **ISOLATED**: No backlinks exist because no documented packages use mutator yet
- Remaining items:
  1. Verify code examples work (manual testing)
