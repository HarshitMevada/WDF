function addMenuButton() {
    let nav = document.querySelector(".nav");

    if (nav) {
        let button = document.createElement("button");
        button.innerHTML = "☰ Menu";
        nav.appendChild(button);

        button.addEventListener("click", function() {
            let links = nav.querySelectorAll("a");

            for (let i = 0; i < links.length; i++) {
                if (links[i].style.display == "none") {
                    links[i].style.display = "block";
                } else {
                    links[i].style.display = "none";
                }
            }
        });
    }
}

function addThemeButton() {
    let nav = document.querySelector(".nav");

    if (nav) {
        let button = document.createElement("button");
        button.innerHTML = "Dark Mode";
        nav.appendChild(button);

        button.addEventListener("click", function() {
            document.body.classList.toggle("dark-theme");

            if (document.body.classList.contains("dark-theme")) {
                localStorage.setItem("theme", "dark");
                button.innerHTML = "Light Mode";
            } else {
                localStorage.setItem("theme", "light");
                button.innerHTML = "Dark Mode";
            }
        });

        if (localStorage.getItem("theme") == "dark") {
            document.body.classList.add("dark-theme");
            button.innerHTML = "Light Mode";
        }
    }
}

function addNotification() {
    let home = document.querySelector(".home");

    if (home) {
        let notification = document.createElement("div");
        notification.className = "notification";
        notification.innerHTML = "New student events are available!";

        let close = document.createElement("button");
        close.innerHTML = "Close";

        notification.appendChild(close);
        home.insertBefore(notification, home.firstChild);

        close.addEventListener("click", function() {
            notification.style.display = "none";
        });
    }
}

function addModal() {
    let home = document.querySelector(".home");

    if (home) {
        let button = document.createElement("button");
        button.innerHTML = "Open Student Notice";
        button.className = "js-button";
        home.appendChild(button);

        let modal = document.createElement("div");
        modal.className = "modal";
        modal.innerHTML = "<div class='modal-box'><h2>Student Notice</h2><p>Remember to check your exam schedule and college events.</p></div>";

        let close = document.createElement("button");
        close.innerHTML = "Close";
        modal.querySelector(".modal-box").appendChild(close);
        document.body.appendChild(modal);

        button.addEventListener("click", function() {
            modal.style.display = "flex";
        });

        close.addEventListener("click", function() {
            modal.style.display = "none";
        });
    }
}

function addSlider() {
    let home = document.querySelector(".home");

    if (home) {
        let slider = document.createElement("div");
        slider.className = "slider";
        slider.innerHTML = "<h2>Student Hub Updates</h2>";

        let slides = [
            "Check your latest attendance.",
            "Register your semester courses.",
            "Check upcoming college events."
        ];

        for (let i = 0; i < slides.length; i++) {
            let item = document.createElement("div");
            item.className = "slide";
            item.innerHTML = slides[i];
            slider.appendChild(item);
        }

        let previous = document.createElement("button");
        previous.innerHTML = "Previous";

        let next = document.createElement("button");
        next.innerHTML = "Next";

        slider.appendChild(previous);
        slider.appendChild(next);
        home.appendChild(slider);

        let slideNumber = 0;
        let allSlides = slider.querySelectorAll(".slide");

        function showSlide() {
            for (let i = 0; i < allSlides.length; i++) {
                allSlides[i].style.display = "none";
            }

            allSlides[slideNumber].style.display = "block";
        }

        next.addEventListener("click", function() {
            slideNumber++;

            if (slideNumber == allSlides.length) {
                slideNumber = 0;
            }

            showSlide();
        });

        previous.addEventListener("click", function() {
            slideNumber--;

            if (slideNumber < 0) {
                slideNumber = allSlides.length - 1;
            }

            showSlide();
        });

        showSlide();
    }
}

function addFAQ() {
    let home = document.querySelector(".home");

    if (home) {
        let faq = document.createElement("div");
        faq.className = "faq";
        faq.innerHTML = "<h2>Frequently Asked Questions</h2>";

        let questions = [
            ["How can I check my result?", "Open the Dashboard and check University Result."],
            ["Where can I register courses?", "Open the Course Registration page."],
            ["Where can I see college events?", "Open the Event Calendar page."]
        ];

        for (let i = 0; i < questions.length; i++) {
            let question = document.createElement("button");
            question.innerHTML = questions[i][0];

            let answer = document.createElement("p");
            answer.innerHTML = questions[i][1];
            answer.style.display = "none";

            faq.appendChild(question);
            faq.appendChild(answer);

            question.addEventListener("click", function() {
                if (answer.style.display == "none") {
                    answer.style.display = "block";
                } else {
                    answer.style.display = "none";
                }
            });
        }

        home.appendChild(faq);
    }
}

function addEventButtons() {
    let buttons = document.querySelectorAll(".event-card button");

    for (let i = 0; i < buttons.length; i++) {
        buttons[i].addEventListener("click", function() {
            alert("You have selected this event.");
        });
    }
}

addMenuButton();
addThemeButton();
addNotification();
addModal();
addSlider();
addFAQ();
addEventButtons();

//practical 5

function registerValidation() {

    let form = document.getElementById("registerForm");

    let name = document.getElementById("name");
    let username = document.getElementById("username");
    let email = document.getElementById("email");
    let mobile = document.getElementById("mobile");
    let password = document.getElementById("password");
    let confirmPassword = document.getElementById("confirmPassword");
    let course = document.getElementById("course");
    let year = document.getElementById("year");
    let terms = document.getElementById("terms");

    let strength = document.getElementById("strength");

    function validateName() {
        let pattern = /^[A-Za-z ]{3,}$/;

        if (!pattern.test(name.value.trim()) || name.value.trim().length > 120) {
            document.getElementById("nameError").innerHTML =
                "Enter a name with 3 to 120 letters and spaces.";
            return false;
        } else {
            document.getElementById("nameError").innerHTML = "";
            return true;
        }
    }

    function validateUsername() {
        let pattern = /^[A-Za-z0-9_]{3,30}$/;

        if (!pattern.test(username.value.trim())) {
            document.getElementById("usernameError").innerHTML =
                "Username must be 3 to 30 letters, numbers, or underscores.";
            return false;
        } else {
            document.getElementById("usernameError").innerHTML = "";
            return true;
        }
    }

    function validateEmail() {
        let pattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

        if (!pattern.test(email.value.trim()) || email.value.trim().length > 254) {
            document.getElementById("emailError").innerHTML =
                "Enter a valid email address.";
            return false;
        } else {
            document.getElementById("emailError").innerHTML = "";
            return true;
        }
    }

    function validateMobile() {
        let pattern = /^[6-9][0-9]{9}$/;

        if (!pattern.test(mobile.value)) {
            document.getElementById("mobileError").innerHTML =
                "Enter a valid 10 digit mobile number.";
            return false;
        } else {
            document.getElementById("mobileError").innerHTML = "";
            return true;
        }
    }

    function validatePassword() {
        let pattern = /^(?=.*[A-Z])(?=.*[a-z])(?=.*[0-9])(?=.*[@#$!%*?&]).{8,}$/;

        if (!pattern.test(password.value)) {
            document.getElementById("passwordError").innerHTML =
                "Use 8 characters, uppercase, lowercase, number and special character.";
            return false;
        } else {
            document.getElementById("passwordError").innerHTML = "";
            return true;
        }
    }

    function validateConfirmPassword() {
        if (confirmPassword.value == "" ||
            confirmPassword.value != password.value) {
            document.getElementById("confirmPasswordError").innerHTML =
                "Passwords do not match.";
            return false;
        } else {
            document.getElementById("confirmPasswordError").innerHTML = "";
            return true;
        }
    }

    function validateCourse() {
        if (!["CSE", "IT", "CE", "Other"].includes(course.value)) {
            document.getElementById("courseError").innerHTML =
                "Please select a course.";
            return false;
        } else {
            document.getElementById("courseError").innerHTML = "";
            return true;
        }
    }

    function validateYear() {
        if (!["1", "2", "3", "4"].includes(year.value)) {
            document.getElementById("yearError").innerHTML =
                "Please select your year.";
            return false;
        } else {
            document.getElementById("yearError").innerHTML = "";
            return true;
        }
    }

    function validateGender() {
        let gender = document.querySelector('input[name="gender"]:checked');

        if (gender == null) {
            document.getElementById("genderError").innerHTML =
                "Please select your gender.";
            return false;
        } else {
            document.getElementById("genderError").innerHTML = "";
            return true;
        }
    }

    function validateTerms() {
        if (!terms.checked) {
            document.getElementById("termsError").innerHTML =
                "Please accept the terms and conditions.";
            return false;
        } else {
            document.getElementById("termsError").innerHTML = "";
            return true;
        }
    }

    function checkStrength() {
        let value = password.value;

        if (value.length == 0) {
            strength.innerHTML = "Enter a password";
        } else if (value.length < 8) {
            strength.innerHTML = "Weak Password";
            strength.style.color = "red";
        } else if (/[A-Z]/.test(value) &&
                /[a-z]/.test(value) &&
                /[0-9]/.test(value) &&
                /[@#$!%*?&]/.test(value)) {
            strength.innerHTML = "Strong Password";
            strength.style.color = "green";
        } else {
            strength.innerHTML = "Medium Password";
            strength.style.color = "orange";
        }
    }

    name.addEventListener("input", validateName);
    username.addEventListener("input", validateUsername);
    email.addEventListener("input", validateEmail);
    mobile.addEventListener("input", validateMobile);
    password.addEventListener("input", function() {
        validatePassword();
        checkStrength();
        validateConfirmPassword();
    });
    confirmPassword.addEventListener("input", validateConfirmPassword);

    course.addEventListener("change", validateCourse);
    year.addEventListener("change", validateYear);
    terms.addEventListener("change", validateTerms);

    let genders = document.querySelectorAll('input[name="gender"]');

    for (let i = 0; i < genders.length; i++) {
        genders[i].addEventListener("change", validateGender);
    }

    form.addEventListener("submit", function(event) {
        event.preventDefault();

        let validName = validateName();
        let validUsername = validateUsername();
        let validEmail = validateEmail();
        let validMobile = validateMobile();
        let validPassword = validatePassword();
        let validConfirmPassword = validateConfirmPassword();
        let validCourse = validateCourse();
        let validYear = validateYear();
        let validGender = validateGender();
        let validTerms = validateTerms();

        if (validName && validUsername && validEmail && validMobile &&
            validPassword && validConfirmPassword &&
            validCourse && validYear && validGender && validTerms) {

            form.submit();
        } else {
            document.getElementById("success").innerHTML = "";
        }
    });
}

if (document.getElementById("registerForm")) {
    registerValidation();
}

//Practical 6

async function loadEvents() {
    let eventList = document.getElementById("eventList");
    let message = document.getElementById("eventMessage");

    let search = document.getElementById("eventSearch");
    let filter = document.getElementById("eventFilter");
    let sort = document.getElementById("eventSort");

    let previous = document.getElementById("previousPage");
    let next = document.getElementById("nextPage");
    let pageNumber = document.getElementById("pageNumber");

    let events = [];
    let currentPage = 1;
    let eventsPerPage = 6;

    try {
        let response = await fetch("events.php");

        if (!response.ok) {
            throw new Error("Unable to load events");
        }

        events = await response.json();

        message.innerHTML = "";
        displayEvents();

    } catch (error) {
        message.innerHTML = "Error loading events. Please try again.";
    }

    // Search, filter and sort events
    function displayEvents() {
        let searchText = search.value.toLowerCase();
        let category = filter.value;
        let sortValue = sort.value;

        let filteredEvents = events.filter(function(event) {
            return event.title.toLowerCase().includes(searchText) &&
                   (category == "All" || event.category == category);
        });

        if (sortValue == "asc") {
            filteredEvents.sort(function(a, b) {
                return new Date(a.date) - new Date(b.date);
            });
        } else if (sortValue == "desc") {
            filteredEvents.sort(function(a, b) {
                return new Date(b.date) - new Date(a.date);
            });
        } else if (sortValue == "az") {
            filteredEvents.sort(function(a, b) {
                return a.title.localeCompare(b.title);
            });
        }

        let totalPages = Math.ceil(filteredEvents.length / eventsPerPage);

        if (currentPage > totalPages) {
            currentPage = totalPages || 1;
        }

        let start = (currentPage - 1) * eventsPerPage;
        let pageEvents = filteredEvents.slice(start, start + eventsPerPage);

        eventList.innerHTML = "";

        if (pageEvents.length == 0) {
            message.innerHTML = "No events found.";
        } else {
            message.innerHTML = "Total events: " + filteredEvents.length;
        }

        for (let i = 0; i < pageEvents.length; i++) {
            let event = pageEvents[i];

            let card = document.createElement("div");
            card.className = "event-card";

            let title = document.createElement("h3");
            title.textContent = event.title;
            card.appendChild(title);

            let category = document.createElement("p");
            let categoryLabel = document.createElement("b");
            categoryLabel.textContent = "Category:";
            category.append(categoryLabel, " ", event.category);
            card.appendChild(category);

            let date = document.createElement("p");
            let dateLabel = document.createElement("b");
            dateLabel.textContent = "Date:";
            date.append(dateLabel, " ", event.date);
            card.appendChild(date);

            let description = document.createElement("p");
            description.textContent = event.description;
            card.appendChild(description);

            let participate = document.createElement("button");
            participate.textContent = "Participate";
            card.appendChild(participate);

            participate.onclick = function() {
                alert("You selected: " + event.title);
            };

            eventList.appendChild(card);
        }

        pageNumber.innerHTML = "Page " + currentPage + " of " + (totalPages || 1);

        previous.disabled = currentPage == 1;
        next.disabled = currentPage == totalPages || totalPages == 0;
    }

    // Search events
    search.addEventListener("input", function() {
        currentPage = 1;
        displayEvents();
    });

    // Filter by category
    filter.addEventListener("change", function() {
        currentPage = 1;
        displayEvents();
    });

    // Sort events
    sort.addEventListener("change", function() {
        currentPage = 1;
        displayEvents();
    });

    // Previous page
    previous.addEventListener("click", function() {
        if (currentPage > 1) {
            currentPage--;
            displayEvents();
        }
    });

    // Next page
    next.addEventListener("click", function() {
        currentPage++;
        displayEvents();
    });
}

// Run Practical 6 only on the Events page
if (document.getElementById("eventList")) {
    loadEvents();

}