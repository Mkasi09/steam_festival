<?php
require_once __DIR__ . '/config.php';

$pageTitle = 'School Participation';
$pdo = db();
$perPage = 20;
$page = max(1, (int) ($_GET['page'] ?? 1));
$offset = ($page - 1) * $perPage;
$query = trim($_GET['q'] ?? '');
$editId = max(0, (int) ($_GET['edit_id'] ?? 0));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'update_school') {
        $id = (int) ($_POST['id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $emis = trim($_POST['emis_number'] ?? '');
        $districtId = (int) ($_POST['district_id'] ?? 0);
        $circuitId = (int) ($_POST['circuit_id'] ?? 0);
        $contact = trim($_POST['contact_person'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $address = trim($_POST['address'] ?? '');

        if ($id < 1 || $name === '' || $districtId < 1 || $circuitId < 1) {
            flash('School, district, and circuit are required.', 'error');
            redirect('admin_schools.php');
        }

        $stmt = $pdo->prepare(
            'UPDATE schools
             SET name = ?, emis_number = ?, district_id = ?, circuit_id = ?, contact_person = ?, phone = ?, email = ?, address = ?
             WHERE id = ?'
        );
        $stmt->execute([
            $name,
            $emis ?: null,
            $districtId,
            $circuitId,
            $contact ?: null,
            formatPhone($phone) ?: null,
            $email ?: null,
            $address ?: null,
            $id,
        ]);
        flash('School updated.');
        redirect('admin_schools.php?page=' . $page . '&q=' . urlencode($query));
    }

    if ($action === 'delete_school') {
        $id = (int) ($_POST['id'] ?? 0);
        if ($id > 0) {
            $stmt = $pdo->prepare('DELETE FROM schools WHERE id = ?');
            $stmt->execute([$id]);
            flash('School deleted.');
        }
        redirect('admin_schools.php');
    }
}

$districts = $pdo->query('SELECT id, name FROM districts ORDER BY name')->fetchAll();
$circuits = $pdo->query('SELECT id, district_id, name FROM circuits ORDER BY name')->fetchAll();
$selectedSchool = null;
if ($query !== '') {
    $countStmt = $pdo->prepare(
        'SELECT COUNT(DISTINCT s.id)
         FROM schools s
         JOIN learners l0 ON l0.school_id = s.id
         LEFT JOIN districts d ON d.id = s.district_id
         LEFT JOIN circuits c ON c.id = s.circuit_id
         WHERE s.name LIKE :q OR s.emis_number LIKE :q OR d.name LIKE :q OR c.name LIKE :q OR s.address LIKE :q'
    );
    $countStmt->execute(['q' => '%' . $query . '%']);
    $total = (int) $countStmt->fetchColumn();

    $stmt = $pdo->prepare(
        'SELECT s.id, s.name, s.emis_number, s.district_id, s.circuit_id, s.contact_person, s.phone, s.email, s.address,
                d.name AS district, c.name AS circuit,
                COUNT(l0.id) AS learner_count,
                SUM(CASE WHEN l0.gender = "Female" THEN 1 ELSE 0 END) AS female_count,
                SUM(CASE WHEN l0.gender = "Male" THEN 1 ELSE 0 END) AS male_count,
                SUM(CASE WHEN l0.gender = "Other" THEN 1 ELSE 0 END) AS other_count
         FROM schools s
         JOIN learners l0 ON l0.school_id = s.id
         LEFT JOIN districts d ON d.id = s.district_id
         LEFT JOIN circuits c ON c.id = s.circuit_id
         WHERE s.name LIKE :q OR s.emis_number LIKE :q OR d.name LIKE :q OR c.name LIKE :q OR s.address LIKE :q
         GROUP BY s.id, s.name, s.emis_number, s.district_id, s.circuit_id, s.contact_person, s.phone, s.email, s.address, d.name, c.name
         ORDER BY learner_count DESC, s.name
         LIMIT :limit OFFSET :offset'
    );
    $stmt->bindValue(':q', '%' . $query . '%');
    $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $schools = $stmt->fetchAll();
} else {
    $total = (int) $pdo->query('SELECT COUNT(DISTINCT school_id) FROM learners')->fetchColumn();
    $stmt = $pdo->prepare(
        'SELECT s.id, s.name, s.emis_number, s.district_id, s.circuit_id, s.contact_person, s.phone, s.email, s.address,
                d.name AS district, c.name AS circuit,
                COUNT(l0.id) AS learner_count,
                SUM(CASE WHEN l0.gender = "Female" THEN 1 ELSE 0 END) AS female_count,
                SUM(CASE WHEN l0.gender = "Male" THEN 1 ELSE 0 END) AS male_count,
                SUM(CASE WHEN l0.gender = "Other" THEN 1 ELSE 0 END) AS other_count
         FROM schools s
         JOIN learners l0 ON l0.school_id = s.id
         LEFT JOIN districts d ON d.id = s.district_id
         LEFT JOIN circuits c ON c.id = s.circuit_id
         GROUP BY s.id, s.name, s.emis_number, s.district_id, s.circuit_id, s.contact_person, s.phone, s.email, s.address, d.name, c.name
         ORDER BY learner_count DESC, s.name
         LIMIT :limit OFFSET :offset'
    );
    $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $schools = $stmt->fetchAll();
}
$totalPages = max(1, (int) ceil($total / $perPage));

if ($editId > 0) {
    $editStmt = $pdo->prepare(
        'SELECT s.id, s.name, s.emis_number, s.district_id, s.circuit_id, s.contact_person, s.phone, s.email, s.address
         FROM schools s
         JOIN learners l0 ON l0.school_id = s.id
         WHERE s.id = ?
         GROUP BY s.id, s.name, s.emis_number, s.district_id, s.circuit_id, s.contact_person, s.phone, s.email, s.address'
    );
    $editStmt->execute([$editId]);
    $selectedSchool = $editStmt->fetch();
}

require __DIR__ . '/includes/header.php';
?>

<section class="panel">
    <div class="panel-heading">
        <div>
            <p class="eyebrow">Participation</p>
            <h2>School Participation</h2>
        </div>
    </div>

    <form method="get" class="school-picker">
        <label>
            Search participating schools
            <input name="q" value="<?= e($query) ?>" placeholder="Search by school, EMIS, district, or circuit">
        </label>
        <button class="button secondary" type="submit">Search</button>
        <a class="button" href="admin_export.php?type=overall">Download Overall Excel</a>
    </form>
    <p class="empty">Showing <?= count($schools) ?> of <?= $total ?> participating schools.</p>

    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>School</th>
                    <th>EMIS</th>
                    <th>District</th>
                    <th>Circuit</th>
                    <th>Address</th>
                    <th>Learners</th>
                    <th>Female</th>
                    <th>Male</th>
                    <th>Other</th>
                    <th>Download</th>
                    <th>Edit</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($schools as $school): ?>
                    <tr>
                        <td><?= e($school['name']) ?></td>
                        <td><?= e($school['emis_number']) ?></td>
                        <td><?= e($school['district']) ?></td>
                        <td><?= e($school['circuit']) ?></td>
                        <td><?= e($school['address']) ?></td>
                        <td><?= (int) $school['learner_count'] ?></td>
                        <td><?= (int) $school['female_count'] ?></td>
                        <td><?= (int) $school['male_count'] ?></td>
                        <td><?= (int) $school['other_count'] ?></td>
                        <td><a href="admin_export.php?type=school&school_id=<?= (int) $school['id'] ?>">Excel</a></td>
                        <td>
                            <a class="icon-button edit-link" href="admin_schools.php?page=<?= $page ?>&q=<?= urlencode($query) ?>&edit_id=<?= (int) $school['id'] ?>#edit-school" aria-label="Edit <?= e($school['name']) ?>">
                                <svg viewBox="0 0 24 24" aria-hidden="true">
                                    <path d="M4 16.5V20h3.5L18.1 9.4l-3.5-3.5L4 16.5z"></path>
                                    <path d="M16 4.5 19.5 8"></path>
                                </svg>
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <?php if ($selectedSchool): ?>
        <form method="post" class="inline-edit-panel" id="edit-school">
            <input type="hidden" name="action" value="update_school">
            <input type="hidden" name="id" value="<?= (int) $selectedSchool['id'] ?>">
            <div class="panel-heading tight">
                <div>
                    <p class="eyebrow">Edit</p>
                    <h2><?= e($selectedSchool['name']) ?></h2>
                </div>
                <a class="button secondary" href="admin_schools.php?page=<?= $page ?>&q=<?= urlencode($query) ?>">Close</a>
            </div>
            <div class="edit-fields admin-fields">
                <label>School<input name="name" value="<?= e($selectedSchool['name']) ?>" required></label>
                <label>EMIS<input name="emis_number" value="<?= e($selectedSchool['emis_number']) ?>"></label>
                <label>
                    District
                    <select name="district_id" class="district-control" required>
                        <option value="">Select district</option>
                        <?php foreach ($districts as $district): ?>
                            <option value="<?= (int) $district['id'] ?>" <?= (int) $selectedSchool['district_id'] === (int) $district['id'] ? 'selected' : '' ?>>
                                <?= e($district['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>
                    Circuit
                    <select name="circuit_id" class="circuit-control" required>
                        <option value="">Select circuit</option>
                        <?php foreach ($circuits as $circuit): ?>
                            <option value="<?= (int) $circuit['id'] ?>" data-district="<?= (int) $circuit['district_id'] ?>" <?= (int) $selectedSchool['circuit_id'] === (int) $circuit['id'] ? 'selected' : '' ?>>
                                <?= e($circuit['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>Contact<input name="contact_person" value="<?= e($selectedSchool['contact_person']) ?>"></label>
                <label>Phone<input name="phone" value="<?= e(formatPhone($selectedSchool['phone'])) ?>"></label>
                <label>Email<input type="email" name="email" value="<?= e($selectedSchool['email']) ?>"></label>
                <label>Address<input name="address" value="<?= e($selectedSchool['address']) ?>"></label>
                <div class="actions">
                    <button class="button" type="submit">Save Changes</button>
                </div>
            </div>
        </form>
    <?php elseif ($editId > 0): ?>
        <div class="notice error">That participating school could not be found.</div>
    <?php endif; ?>

    <div class="admin-nav">
        <?php if ($page > 1): ?>
            <a class="button secondary" href="admin_schools.php?page=<?= $page - 1 ?>&q=<?= urlencode($query) ?>">Previous</a>
        <?php endif; ?>
        <?php if ($page < $totalPages): ?>
            <a class="button secondary" href="admin_schools.php?page=<?= $page + 1 ?>&q=<?= urlencode($query) ?>">Next</a>
        <?php endif; ?>
    </div>
</section>

<script>
document.querySelectorAll('.inline-edit-panel').forEach((card) => {
    const district = card.querySelector('.district-control');
    const circuit = card.querySelector('.circuit-control');
    if (!district || !circuit) return;
    const options = [...circuit.querySelectorAll('option')];
    function filterCircuits() {
        const districtId = district.value;
        const current = circuit.value;
        options.forEach((option) => {
            option.hidden = option.value !== '' && option.dataset.district !== districtId;
        });
        const valid = options.some((option) => option.value === current && !option.hidden);
        if (!valid) circuit.value = '';
    }
    district.addEventListener('change', filterCircuits);
    filterCircuits();
});
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>
