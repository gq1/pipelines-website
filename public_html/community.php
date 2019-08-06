<?php
// ini_set('display_errors', 1);
// ini_set('display_startup_errors', 1);
// error_reporting(E_ALL);

$title = 'Community';
$subtitle = 'Find out who is involved in the nf-core project';
$md_github_url = 'https://github.com/nf-core/nf-co.re/blob/master/nf-core-contributors.yaml';
include('../includes/header.php');

?>

<h1>Introduction</h1>
<p>nf-core is by design a collaborative effort, and would not exist if it were not for the efforts of many dedicated contributors.</p>
<ul>
    <li><a href="#contributors">Contributors</a></li>
    <li><a href="#organisations">Organisations</a></li>
    <li><a href="#testimonials">Testimonials</a></li>
</ul>


<h2 id="contributors"><a href="#contributors" class="header-link"><span class="fas fa-link" aria-hidden="true"></span></a>Contributors</h2>
<p>The nf-core pipelines and community is driven by many individuals, listed below. This list updates automatically.</p>
<p>Want to see who's working on what? See the <a href="/stats#contributor_leaderboard">contributor leaderboard</a> on the Statistics page.</p>
<p class="pt-3">
<?php
$stats_json_fn = dirname(dirname(__FILE__)).'/nfcore_stats.json';
$stats_json = json_decode(file_get_contents($stats_json_fn));
$contributors = [];
foreach(['pipelines', 'core_repos'] as $repo_type){
    foreach($stats_json->{$repo_type} as $repo){
        foreach($repo->contributors as $contributor){
            $contributors[$contributor->author->login] = $contributor->author;
        }
    }
}
// Random order!
$logins = array_keys($contributors);
shuffle($logins);
foreach($logins as $login){
    $author = $contributors[$login];
    echo '<a title="@'.$author->login.'" href="'.$author->html_url.'" target="_blank" data-toggle="tooltip"><img src="'.$author->avatar_url.'" class="border rounded-circle mr-1 mb-1" width="50" height="50"></a>';
}
?>
</p>

<h2 id="organisations"><a href="#organisations" class="header-link"><span class="fas fa-link" aria-hidden="true"></span></a>Organisations</h2>
<p>Some of the organisations running nf-core pipelines are listed below, along with a key person who you can contact for advice.</p>
<blockquote>Is your group missing? Please submit a pull request to add yourself! It's just a few lines in a simple YAML file..</blockquote>

<div class="card contributors-map-card">
    <div class="card-body" id="contributors-map"></div>
</div>
<div class="card-deck">

<?php
// Parse YAML contributors file
$locations = [];
require_once("../Spyc.php");
$contributors = spyc_load_file('../nf-core-contributors.yaml');
$contributors_html = '';
foreach($contributors['contributors'] as $idx => $c){
    // Start card div
    $contributors_html .= '<div class="card contributor card_deck_card"><div class="card-body">';
    // Header, title
    $img_path = '';
    if(array_key_exists('image_fn', $c)){
        $img_path = 'assets/img/contributors-colour/'.$c['image_fn'];
        if($c['image_fn'] and file_exists($img_path))
            $contributors_html .= '<img class="contributor_logo" title="'.$c['full_name'].'" src="'.$img_path.'">';
        else $img_path = '';
    }
    $card_id = $idx;
    if(array_key_exists('full_name', $c)){
        $card_id = preg_replace('/[^a-z]+/', '-', strtolower($c['full_name']));
        $contributors_html .= '<h5 class="card-title" id="'.$card_id.'">';
        if(array_key_exists('url', $c))
            $contributors_html .= ' <a href="'.$c['url'].'" target="_blank">';
        $contributors_html .= $c['full_name'];
        if(array_key_exists('url', $c))
            $contributors_html .= ' </a>';
        $contributors_html .= '</h5>';
    }
    if(array_key_exists('affiliation', $c)){
        $contributors_html .= '<h6 class="card-subtitle mb-2 text-muted">';
        if(array_key_exists('affiliation_url', $c))
            $contributors_html .= '<a href="'.$c['affiliation_url'].'" target="_blank">';
        $contributors_html .= $c['affiliation'];
        if(array_key_exists('affiliation_url', $c))
            $contributors_html .= '</a>';
        $contributors_html .= '</h6>';
    }
    // Description
    if(array_key_exists('description', $c))
        $contributors_html .= '<p class="small text-muted">'.$c['description'].'</p> ';
    // Contact person
    $contributors_html .= '<div class="contributor_contact">';
    if(array_key_exists('contact_email', $c)){
        $contributors_html .= '<a href="mailto:'.$c['contact_email'].'" class="badge badge-light" data-toggle="tooltip" title="Primary contact: '.$c['contact_email'].'"><i class="far fa-envelope"></i> ';
        if(array_key_exists('contact', $c))
            $contributors_html .= $c['contact'];
        else
            $contributors_html .= $c['contact_email'];
        $contributors_html .= '</a> ';
    }
    else if(array_key_exists('contact', $c))
        $contributors_html .= '<span class="badge badge-light">'.$c['contact'].'</span> ';
    if(array_key_exists('contact_github', $c))
        $contributors_html .= '<a href="https://github.com/'.trim($c['contact_github'], '@').'/" target="_blank" class="badge badge-light" data-toggle="tooltip" title="Primary contact: GitHub @'.trim($c['contact_github'], '@').'"><i class="fab fa-github"></i> '.trim($c['contact_github'], '@').'</a> ';
    if(array_key_exists('twitter', $c))
        $contributors_html .= '<a href="https://twitter.com/'.trim($c['twitter'], '@').'/" target="_blank" class="badge badge-light" data-toggle="tooltip" title="Institutional twitter: @'.trim($c['twitter'], '@').'"><i class="fab fa-twitter"></i> @'.trim($c['twitter'], '@').'</a> ';
    $contributors_html .= '</div>';
    // Close card div
    $contributors_html .= '</div></div>';

    // Location JSON
    if(array_key_exists('location', $c)){
        $location['location'] = $c['location'];
        $location['full_name'] = array_key_exists('full_name', $c) ? $c['full_name'] : '';
        $location['card_id'] = $card_id;
        if($img_path){
            $location['image'] = '<br><a href="#'.$card_id.'"><img class="contributor_map_logo" title="'.$location['full_name'].'" src="'.$img_path.'"></a>';
        } else $location['image'] = '';
        array_push($locations, $location);
    }
}

echo $contributors_html;
?>

</div>
<script type="text/javascript">
var locations = <?php echo json_encode($locations, JSON_PRETTY_PRINT); ?>;

$(function(){
    var map = L.map('contributors-map', {
        zoom: 2
    });
    var greenIcon = new L.Icon({
        iconUrl: 'assets/img/marker-icon-2x-green.png',
        shadowUrl: 'assets/img/marker-shadow.png',
        iconSize: [25, 41],
        iconAnchor: [12, 41],
        popupAnchor: [1, -34],
        shadowSize: [41, 41]
    });

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
    }).addTo(map);

    var latlngs = [];
    locations.forEach(function(marker) {
        if (marker != null) {
            L.marker(marker.location, {icon: greenIcon}).addTo(map).bindPopup('<a href="#'+marker.card_id+'">'+marker.full_name+'</a>'+marker.image);
            latlngs.push(marker.location);
        }
    });
    map.fitBounds(latlngs);
});
</script>

<h2 id="testimonials"><a href="#testimonials" class="header-link"><span class="fas fa-link" aria-hidden="true"></span></a>Testimonials</h2>
<p>We are collating statements and general comments from those who have either contributed to nf-core, or have chosen to routinely deploy
    nf-core pipelines for their data analysis. Feel free to add your own experiences, and please let us know if we have missed anything!</p>
<h3 id="dfg_testimonial"><a href="#dfg_testimonial" class="header-link"><span class="fas fa-link" aria-hidden="true"></span></a>
    <img height="45px" src="/assets/img/dfg_logo.svg" class="float-right pl-4" />
    German National Sequencing Initiative
</h3>
<p><a href="https://www.dfg.de/en/service/press/press_releases/2018/press_release_no_06/index.html" target="_blank">The German Funding Body (DFG)</a>
has approved funding to establish 4 national high-throughput sequencing centers in Germany. The project will rely on <em>nf-core</em> pipelines for analyzing
large-scale genomics data. Contributors from the Kiel and Tübingen sites are already actively contributing to nf-core, and the other sequencing centers
in Cologne/Bonn (West German Genome Center) and the Dresden Center are in the process of joining and contributing their expertise too!</p>


<?php include('../includes/footer.php');