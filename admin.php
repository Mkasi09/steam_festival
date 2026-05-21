<?php
session_start();

if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: admin_login.php');
    exit;
}
?>


<?php
require_once __DIR__ . '/config.php';

$pageTitle = 'Participation Overview';
$pdo = db();

$schoolCount = (int) $pdo->query('SELECT COUNT(DISTINCT school_id) FROM learners')->fetchColumn();
$learnerCount = (int) $pdo->query('SELECT COUNT(*) FROM learners')->fetchColumn();
$teacherCount = (int) $pdo->query('SELECT COUNT(*) FROM teachers')->fetchColumn();
$genderStats = $pdo->query(
    'SELECT
        SUM(CASE WHEN gender = "Female" THEN 1 ELSE 0 END) AS female_count,
        SUM(CASE WHEN gender = "Male" THEN 1 ELSE 0 END) AS male_count,
        SUM(CASE WHEN gender = "Other" THEN 1 ELSE 0 END) AS other_count
     FROM learners'
)->fetch();

$teacherGenderStats = $pdo->query(
    'SELECT
        SUM(CASE WHEN gender = "Female" THEN 1 ELSE 0 END) AS female_count,
        SUM(CASE WHEN gender = "Male" THEN 1 ELSE 0 END) AS male_count,
        SUM(CASE WHEN gender = "Other" THEN 1 ELSE 0 END) AS other_count
     FROM teachers'
)->fetch();

$latestSchools = $pdo->query(
    'SELECT s.id, s.name, s.emis_number, s.address, d.name AS district, c.name AS circuit, MAX(l.id) AS latest_learner_id
     FROM schools s
     LEFT JOIN districts d ON d.id = s.district_id
     LEFT JOIN circuits c ON c.id = s.circuit_id
     JOIN learners l ON l.school_id = s.id
     GROUP BY s.id, s.name, s.emis_number, s.address, d.name, c.name
     ORDER BY latest_learner_id DESC
     LIMIT 10'
)->fetchAll();

$participation = $pdo->query(
    'SELECT s.id, s.name, s.emis_number, s.address, d.name AS district, c.name AS circuit,
            COUNT(l.id) AS learner_count,
            SUM(CASE WHEN l.gender = "Female" THEN 1 ELSE 0 END) AS female_count,
            SUM(CASE WHEN l.gender = "Male" THEN 1 ELSE 0 END) AS male_count,
            SUM(CASE WHEN l.gender = "Other" THEN 1 ELSE 0 END) AS other_count
     FROM schools s
     LEFT JOIN districts d ON d.id = s.district_id
     LEFT JOIN circuits c ON c.id = s.circuit_id
     JOIN learners l ON l.school_id = s.id
     GROUP BY s.id, s.name, s.emis_number, s.address, d.name, c.name
     ORDER BY learner_count DESC, s.name'
)->fetchAll();

require __DIR__ . '/includes/header.php';
?>

<section class="panel">
    <div class="panel-heading">
        <div>
            <p class="eyebrow">Participation</p>
            <h2>Overview</h2>
        </div>
        <div class="panel-actions">
    <a class="button secondary" href="admin_schools.php">View Schools</a>
    <a class="button secondary" href="admin_learners.php">View Learners</a>
    <a class="button secondary" href="admin_teachers.php">View Teachers</a>

    <a class="button danger" href="logout.php">Logout</a>
</div>
    </div>

    <div class="stats">
        <article><span>Participating Schools</span><strong><?= $schoolCount ?></strong></article>
        <article><span>Learner Entries</span><strong><?= $learnerCount ?></strong></article>
        <article><span>Teacher Entries</span><strong><?= $teacherCount ?></strong></article>
        
    </div>
</section>

<section class="panel">
    <div class="panel-heading">
        <div>
            <p class="eyebrow">Participation</p>
            <h2>Learner Stats</h2>
        </div>
    </div>
    <div class="stats">
        <article><span>Female</span><strong><?= (int) ($genderStats['female_count'] ?? 0) ?></strong></article>
        <article><span>Male</span><strong><?= (int) ($genderStats['male_count'] ?? 0) ?></strong></article>
        <article><span>Other</span><strong><?= (int) ($genderStats['other_count'] ?? 0) ?></strong></article>
    </div>
</section>

<section class="panel">
    <div class="panel-heading">
        <div>
            <p class="eyebrow">Participation</p>
            <h2>Teacher Stats</h2>
        </div>
    </div>
    <div class="stats">
        <article><span>Female</span><strong><?= (int) ($teacherGenderStats['female_count'] ?? 0) ?></strong></article>
        <article><span>Male</span><strong><?= (int) ($teacherGenderStats['male_count'] ?? 0) ?></strong></article>
        <article><span>Other</span><strong><?= (int) ($teacherGenderStats['other_count'] ?? 0) ?></strong></article>
    </div>
</section>


<section class="panel">
    <div class="panel-heading">
        <div>
            <p class="eyebrow">Participation</p>
            <h2>Schools and Learners</h2>
        </div>
    </div>
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
                <?php foreach ($participation as $row): ?>
                    <tr>
                        <td><?= e($row['name']) ?></td>
                        <td><?= e($row['emis_number']) ?></td>
                        <td><?= e($row['district']) ?></td>
                        <td><?= e($row['circuit']) ?></td>
                        <td><?= e($row['address']) ?></td>
                        <td><?= (int) $row['learner_count'] ?></td>
                        <td><?= (int) $row['female_count'] ?></td>
                        <td><?= (int) $row['male_count'] ?></td>
                        <td><?= (int) $row['other_count'] ?></td>
                        <td><a href="admin_export.php?type=school&school_id=<?= (int) $row['id'] ?>">Excel</a></td>
                        <td>
                            <a class="icon-button edit-link" href="admin_schools.php?edit_id=<?= (int) $row['id'] ?>#edit-school" aria-label="Edit <?= e($row['name']) ?>">
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
</section>
<section class="panel">

    <div class="panel-heading">
        <div>
            <p class="eyebrow">Analytics</p>
            <h2>Participation Graph</h2>
        </div>
    </div>

    <div class="chart-container">
        <canvas id="overviewChart"></canvas>
    </div>

</section>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>

const ctx = document.getElementById('overviewChart');

new Chart(ctx, {

    type: 'bar',

    data: {

        labels: [
            'Schools',
            'Learners',
            'Teachers',
            'Female Learners',
            'Male Learners',
            'Other Learners',
            'Female Teachers',
            'Male Teachers',
            'Other Teachers'
        ],

        datasets: [{

            label: 'Participation Statistics',

            data: [

                <?= $schoolCount ?>,
                <?= $learnerCount ?>,
                <?= $teacherCount ?>,

                <?= (int) ($genderStats['female_count'] ?? 0) ?>,
                <?= (int) ($genderStats['male_count'] ?? 0) ?>,
                <?= (int) ($genderStats['other_count'] ?? 0) ?>,

                <?= (int) ($teacherGenderStats['female_count'] ?? 0) ?>,
                <?= (int) ($teacherGenderStats['male_count'] ?? 0) ?>,
                <?= (int) ($teacherGenderStats['other_count'] ?? 0) ?>

            ],

            borderWidth: 1,
            borderRadius: 8

        }]

    },

    options: {

        responsive: true,

        plugins: {

            legend: {
                display: false
            }

        },

        scales: {

            y: {

                beginAtZero: true,

                ticks: {
                    precision: 0
                }

            }

        }

    }

});

</script>
<?php require __DIR__ . '/includes/footer.php'; ?>
