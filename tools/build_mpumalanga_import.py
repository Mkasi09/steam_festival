from pathlib import Path

import openpyxl


def clean(value):
    return "" if value is None else str(value).strip()


def quote(value):
    if value == "":
        return "NULL"
    return "'" + value.replace("\\", "\\\\").replace("'", "''") + "'"


def phone(value):
    digits = "".join(ch for ch in value if ch.isdigit())
    if len(digits) == 9 and not digits.startswith("0"):
        return "0" + digits
    return value


workbook = openpyxl.load_workbook("mpumalanga_q3_2025.xlsx", read_only=True, data_only=True)
sheet = workbook.active
rows = sheet.iter_rows(values_only=True)
headers = {name: index for index, name in enumerate(next(rows))}

schools = []
districts = set()
circuits = set()

for row in rows:
    item = {key: clean(row[index]) for key, index in headers.items()}
    if item["Status"].upper() != "OPEN":
        continue

    district = item["EIDistrict"].title()
    circuit = item["EICircuit"].title()
    if not circuit or circuit.lower() == "unknown":
        circuit = item["LMunName"].title() or item["DMunName"].title() or "Circuit Not Captured"
    emis = item["NatEmis"]
    name = item["Official_Institution_Name"].title()

    if not emis or not name or not district or not circuit:
        continue

    address = ", ".join(
        part
        for part in [
            item["StreetAddress"],
            item["Township_Village"],
            item["Suburb"],
            item["Town_City"],
        ]
        if part
    ).title()

    schools.append(
        {
            "emis": emis,
            "name": name,
            "district": district,
            "circuit": circuit,
            "contact": item["Addressee"].title(),
            "phone": phone(item["Telephone"]),
            "email": item["Email"].lower(),
            "address": address,
        }
    )
    districts.add(district)
    circuits.add((district, circuit))

lines = [
    "USE steam_festival;",
    "",
    "ALTER TABLE schools ADD UNIQUE KEY IF NOT EXISTS unique_school_emis (emis_number);",
    "",
]

for district in sorted(districts):
    lines.append(f"INSERT IGNORE INTO districts (name) VALUES ({quote(district)});")

lines.append("")

for district, circuit in sorted(circuits):
    lines.append(
        "INSERT IGNORE INTO circuits (district_id, name) "
        f"SELECT id, {quote(circuit)} FROM districts WHERE name = {quote(district)};"
    )

lines.append("")

for school in schools:
    lines.append(
        "INSERT INTO schools "
        "(name, emis_number, district_id, circuit_id, contact_person, phone, email, address) "
        f"SELECT {quote(school['name'])}, {quote(school['emis'])}, d.id, c.id, "
        f"{quote(school['contact'])}, {quote(school['phone'])}, {quote(school['email'])}, {quote(school['address'])} "
        "FROM districts d JOIN circuits c ON c.district_id = d.id "
        f"WHERE d.name = {quote(school['district'])} AND c.name = {quote(school['circuit'])} "
        "ON DUPLICATE KEY UPDATE "
        "name = VALUES(name), district_id = VALUES(district_id), circuit_id = VALUES(circuit_id), "
        "contact_person = VALUES(contact_person), phone = VALUES(phone), "
        "email = VALUES(email), address = VALUES(address);"
    )

Path("database/import_mpumalanga_schools.sql").write_text("\n".join(lines) + "\n", encoding="utf-8")

reset_lines = [
    "USE steam_festival;",
    "SET FOREIGN_KEY_CHECKS = 0;",
    "TRUNCATE TABLE learners;",
    "TRUNCATE TABLE schools;",
    "TRUNCATE TABLE circuits;",
    "TRUNCATE TABLE districts;",
    "SET FOREIGN_KEY_CHECKS = 1;",
    "",
]

Path("database/reset_and_import_mpumalanga.sql").write_text(
    "\n".join(reset_lines + lines[1:] + ["", "SOURCE database/circuit_overrides.sql;"]) + "\n",
    encoding="utf-8"
)

print(f"Generated {len(schools)} schools, {len(districts)} districts, {len(circuits)} circuits")
