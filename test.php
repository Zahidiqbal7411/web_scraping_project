<script>
        document.addEventListener("DOMContentLoaded", function() {

            const form = document.getElementById("transactionsForm");
            const totalInput = document.getElementById("transaction_total");
            const paidInput = document.getElementById("transaction_paid");
            const remainingInput = document.getElementById("transaction_remaining");
            const transactionsModal = document.getElementById('transactionsModal');
            const tableBody = document.getElementById(
            'transactionsTableBody'); // Updated to match your table structure

            // 1️⃣ Calculate remaining dynamically
            function calculateRemaining() {
                const total = parseFloat(totalInput.value) || 0;
                const paid = parseFloat(paidInput.value) || 0;
                remainingInput.value = (total - paid >= 0 ? (total - paid).toFixed(2) : 0);
            }

            totalInput.addEventListener("input", calculateRemaining);
            paidInput.addEventListener("input", calculateRemaining);

            // 2️⃣ Submit form via AJAX only once
            let isSubmitting = false; // Prevent double submission

            if (!form.dataset.listener) {
                form.dataset.listener = "true";

                form.addEventListener("submit", async function(e) {
                    e.preventDefault();

                    // Prevent multiple simultaneous submissions
                    if (isSubmitting) {
                        console.log("Form is already being submitted");
                        return;
                    }

                    isSubmitting = true;

                    // Log form data for debugging
                    const formData = new FormData(form);
                    console.log("Submitting form data:");
                    for (let [key, value] of formData.entries()) {
                        console.log(key, ":", value);
                    }

                    try {
                        // Get fresh CSRF token
                        const csrfToken = document.querySelector('input[name="_token"]')?.value ||
                            document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

                        const res = await fetch("{{ route('platform_transaction.store') }}", {
                            method: "POST",
                            body: formData,
                            headers: {
                                "X-CSRF-TOKEN": csrfToken
                            }
                        });

                        // Read response text only once
                        const text = await res.text();
                        console.log("Server Response Status:", res.status);
                        console.log("Server Response Text:", text);

                        // Parse JSON safely
                        let data;
                        try {
                            data = JSON.parse(text);
                            console.log("Parsed Data:", data);
                        } catch (parseErr) {
                            console.error("Server response is not valid JSON:", text);
                            isSubmitting = false;
                            throw new Error("Server did not return valid JSON");
                        }

                        // ✅ Success: transaction saved
                        if (res.ok && data.status === "success" && data.transaction) {

                            if (typeof toastr !== "undefined") {
                                toastr.clear();
                                toastr.success(data.message || "Transaction saved successfully");
                            }

                            // Reset form and close modal
                            form.reset();
                            remainingInput.value = "";

                            // Reset submission flag before closing modal
                            isSubmitting = false;

                            const modalCloseBtn = document.querySelector(
                                '#transactionsModal .btn-close');
                            if (modalCloseBtn) modalCloseBtn.click();

                            // 🎯 Insert new row with smooth animation
                            if (tableBody) {
                                const newRow = document.createElement('tr');

                                // Add a special class for the new row
                                newRow.classList.add('new-transaction-row');

                                // Initial animation styles
                                newRow.style.opacity = '0';
                                newRow.style.transform = 'scale(0.95) translateY(-20px)';
                                newRow.style.transition = 'all 0.6s cubic-bezier(0.34, 1.56, 0.64, 1)';

                                newRow.innerHTML = `
                                <td>${data.transaction.id}</td>
                                <td>${data.transaction.created_at}</td>
                                <td>${data.transaction.teacher || '-'}</td>
                                <td>${data.transaction.course || '-'}</td>
                                <td>${data.transaction.session || '-'}</td>
                                <td>${data.transaction.student_name || '-'}</td>
                                <td>${data.transaction.parent_name || '-'}</td>
                                <td>${Number(data.transaction.total).toFixed(2)}</td>
                                <td>${Number(data.transaction.paid_amount).toFixed(2)}</td>
                                <td>${Number(data.transaction.remaining).toFixed(2)}</td>
                                <td class="text-end">
                                    <button class="btn btn-sm icon-btn restore-btn"
                                        data-id="${data.transaction.id}"
                                        data-total="${data.transaction.total}"
                                        data-paid="${data.transaction.paid_amount}"
                                        data-remaining="${data.transaction.remaining}">
                                        <i class="bi bi-arrow-counterclockwise"></i>
                                    </button>
                                    <button class="btn btn-sm icon-btn text-danger delete-btn">
                                        <i class="bi bi-trash3-fill"></i>
                                    </button>
                                </td>
                            `;

                                // Insert at the top of table for recent transactions
                                tableBody.insertBefore(newRow, tableBody.firstChild);

                                // Trigger slide and fade-in animation
                                setTimeout(() => {
                                    newRow.style.opacity = '1';
                                    newRow.style.transform = 'scale(1) translateY(0)';
                                }, 10);

                                // Add pulsing highlight effect
                                setTimeout(() => {
                                    newRow.style.backgroundColor = '#28a745';
                                    newRow.style.color = '#ffffff';
                                    newRow.style.boxShadow = '0 0 20px rgba(40, 167, 69, 0.6)';

                                    // First pulse
                                    setTimeout(() => {
                                        newRow.style.backgroundColor = '#d4edda';
                                        newRow.style.color = '#155724';
                                        newRow.style.boxShadow =
                                            '0 0 10px rgba(40, 167, 69, 0.3)';
                                    }, 600);

                                    // Second pulse
                                    setTimeout(() => {
                                        newRow.style.backgroundColor = '#28a745';
                                        newRow.style.color = '#ffffff';
                                        newRow.style.boxShadow =
                                            '0 0 20px rgba(40, 167, 69, 0.6)';
                                    }, 1200);

                                    // Final fade to light green
                                    setTimeout(() => {
                                        newRow.style.backgroundColor = '#d4edda';
                                        newRow.style.color = '#155724';
                                        newRow.style.boxShadow = 'none';
                                    }, 1800);

                                    // Return to normal
                                    setTimeout(() => {
                                        newRow.style.backgroundColor = '';
                                        newRow.style.color = '';
                                        newRow.style.transition = 'all 1.5s ease';
                                        newRow.classList.remove('new-transaction-row');
                                    }, 3500);

                                }, 600);

                                // Smooth scroll to the new row
                                setTimeout(() => {
                                    newRow.scrollIntoView({
                                        behavior: "smooth",
                                        block: "center",
                                        inline: "nearest"
                                    });
                                }, 150);
                            }

                        }
                        // 422 validation errors
                        else if (res.status === 422 && data.errors) {
                            const messages = Object.values(data.errors).flat().join("<br>");
                            if (typeof toastr !== "undefined") {
                                toastr.clear();
                                toastr.error(messages);
                            }
                            isSubmitting = false; // Reset flag on error

                        }
                        // Other server errors
                        else {
                            if (typeof toastr !== "undefined") {
                                toastr.clear();
                                toastr.error(data.message || "Server error while saving transaction");
                            }
                            isSubmitting = false; // Reset flag on error
                        }

                    } catch (err) {
                        console.error("AJAX/JS Error:", err);
                        if (typeof toastr !== "undefined") {
                            toastr.clear();
                            toastr.error(err.message ||
                                "Unexpected error occurred while saving transaction");
                        }
                        isSubmitting = false; // Reset flag on exception
                    }
                });
            }

            // 3️⃣ Reset form when modal closes
            transactionsModal.addEventListener('hidden.bs.modal', function() {
                form.reset();
                remainingInput.value = "";
                isSubmitting = false; // Reset submission flag when modal closes

                // Remove any lingering backdrops
                document.querySelectorAll('.modal-backdrop').forEach(el => el.remove());
                document.body.classList.remove('modal-open');
                document.body.style.overflow = '';
                document.body.style.paddingRight = '';
            });

        });
    </script>