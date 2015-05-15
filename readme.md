## How to query relationships

### Find all sections in a relationship:

```sql
select
    section_id
from
    tbl_relationships_sections
where
    relationship_id = :relationship_id
```

### Find sections linked to the current section:

```sql
select
    section_id
from
    tbl_relationships_sections
where
    relationship_id = :relationship_id
    and section_id != :section_id
```

### Find the field assigned to a relationship:

```sql
select
	field_id
from
	tbl_relationships_fields
where
	relationship_id = :relationship_id
	and section_id = :section_id
```

### Find all entries in a relationship:

```sql
select distinct
	entries.id
from
	sym_entries as entries,
	sym_relationships_entries as links
where
	links.relationship_id = 1
	and (
		links.left_entry_id = entries.id
		or links.right_entry_id = entries.id
	)
```

### Find entries linked to a section:

```sql
select distinct
	entries.id
from
	sym_entries as entries,
	sym_relationships_entries as links
where
	entries.section_id = 2
	and links.relationship_id = 1
	and (
		links.left_entry_id = entries.id
		or links.right_entry_id = entries.id
	)
```

### Find all entries linked to an entry:

```sql
select
	case when
		left_entry_id = :entry_id
	then
		right_entry_id
	else
		left_entry_id
	end as id
from
    sym_relationships_entries
where
    relationship_id = :relationship_id
    and (
        left_entry_id = :left_entry_id
        or right_entry_id = :right_entry_id
    )
```

### Create a link between entries:

```sql
insert into
	tbl_relationships_entries
values
	(null, :relationship_id, :left_section_id, :left_entry_id),
	(null, :relationship_id, :right_section_id, :right_entry_id)