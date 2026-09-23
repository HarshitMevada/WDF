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
