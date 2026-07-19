const modal = document.getElementById("assetModal");

const openButton = document.getElementById("openAssetModal");

const closeButton = document.querySelector(".close-modal");

openButton.onclick = function(){

    modal.style.display = "block";

}

closeButton.onclick = function(){

    modal.style.display = "none";

}

window.onclick = function(event){

    if(event.target == modal){

        modal.style.display = "none";

    }

}




function generateAssetID(){

    const randomNumber =
        Math.floor(Math.random()*999999)+1;

    document.getElementById("assetID").value =
        "AST-" +
        String(randomNumber)
        .padStart(6,'0');

}

openButton.onclick = function(){

    generateAssetID();

    modal.style.display="block";

}





document
.getElementById("acquisitionDate")
.max =
new Date()
.toISOString()
.split("T")[0];



const searchInput = document.getElementById("searchInput");

searchInput.addEventListener("keyup", function(){

    let filter = this.value.toLowerCase();

    let rows = document.querySelectorAll(".asset-row");

    rows.forEach(function(row){

        let text = row.innerText.toLowerCase();

        if(text.includes(filter)){

            row.style.display="";

        }else{

            row.style.display="none";

        }

    });

});









const categoryFilter = document.getElementById("categoryFilter");
const statusFilter = document.getElementById("statusFilter");

function applyFilters() {

    const search = searchInput.value.toLowerCase();
    const category = categoryFilter.value.toLowerCase();
    const status = statusFilter.value.toLowerCase();

    const rows = document.querySelectorAll("#assetTable tbody tr");

    rows.forEach(function(row){

        const assetID = row.cells[0].textContent.toLowerCase();
        const assetName = row.cells[1].textContent.toLowerCase();
        const assetCategory = row.cells[2].textContent.toLowerCase();
        const assetStatus = row.cells[4].textContent.toLowerCase();

        const matchesSearch =
            assetID.includes(search) ||
            assetName.includes(search);

        const matchesCategory =
            category === "" || assetCategory === category;

        const matchesStatus =
            status === "" || assetStatus === status;

        row.style.display =
            (matchesSearch && matchesCategory && matchesStatus)
            ? ""
            : "none";

    });

}

searchInput.addEventListener("keyup", applyFilters);
categoryFilter.addEventListener("change", applyFilters);
statusFilter.addEventListener("change", applyFilters);