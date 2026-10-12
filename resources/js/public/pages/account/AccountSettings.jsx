import { useQueryClient } from "@tanstack/react-query";
import { useId, useState } from "react";
import { useNavigate } from "react-router";
import {
    api,
    authHttp,
    ensureCsrfCookie,
    toFormErrors,
} from "../../api/client.js";
import FormField from "../../components/ui/FormField.jsx";

/**
 * Profile, password and account deletion for registered visitors (Phase 11A). Profile and
 * password use Fortify's endpoints; a changed e-mail address must be verified again.
 */
export function ProfileForm({ me }) {
    const queryClient = useQueryClient();
    const [values, setValues] = useState({ name: me.name, email: me.email });
    const [errors, setErrors] = useState({ fields: {} });
    const [status, setStatus] = useState("");
    const [saving, setSaving] = useState(false);

    async function submit(event) {
        event.preventDefault();
        setSaving(true);
        setErrors({ fields: {} });
        setStatus("");
        try {
            await ensureCsrfCookie();
            await authHttp.put("/user/profile-information", values);
            await queryClient.invalidateQueries({ queryKey: ["me"] });
            setStatus(
                values.email !== me.email
                    ? "Saved. Please verify your new e-mail address with the link we sent you."
                    : "Saved.",
            );
        } catch (error) {
            setErrors(toFormErrors(error));
        } finally {
            setSaving(false);
        }
    }

    return (
        <section
            className="pa-account-section"
            aria-labelledby="profile-heading"
        >
            <h2 id="profile-heading" className="h5">
                Your details
            </h2>
            <form onSubmit={submit} noValidate>
                <FormField
                    label="Name"
                    name="name"
                    autoComplete="name"
                    required
                    value={values.name}
                    onChange={(name) => setValues({ ...values, name })}
                    error={errors.fields.name}
                />
                <FormField
                    label="E-mail"
                    name="email"
                    type="email"
                    autoComplete="email"
                    required
                    value={values.email}
                    onChange={(email) => setValues({ ...values, email })}
                    error={errors.fields.email}
                />
                <button
                    type="submit"
                    className="btn btn-primary"
                    disabled={saving}
                >
                    {saving ? "Saving…" : "Save"}
                </button>
                <span className="ms-3 small text-success" role="status">
                    {status}
                </span>
            </form>
        </section>
    );
}

export function PasswordForm() {
    const empty = {
        current_password: "",
        password: "",
        password_confirmation: "",
    };
    const [values, setValues] = useState(empty);
    const [errors, setErrors] = useState({ fields: {} });
    const [status, setStatus] = useState("");
    const [saving, setSaving] = useState(false);

    async function submit(event) {
        event.preventDefault();
        setSaving(true);
        setErrors({ fields: {} });
        setStatus("");
        try {
            await ensureCsrfCookie();
            await authHttp.put("/user/password", values);
            setValues(empty);
            setStatus("Your password has been changed.");
        } catch (error) {
            setErrors(toFormErrors(error));
        } finally {
            setSaving(false);
        }
    }

    return (
        <section
            className="pa-account-section"
            aria-labelledby="password-heading"
        >
            <h2 id="password-heading" className="h5">
                Password
            </h2>
            <form onSubmit={submit} noValidate>
                <FormField
                    label="Current password"
                    name="current_password"
                    type="password"
                    autoComplete="current-password"
                    required
                    value={values.current_password}
                    onChange={(current_password) =>
                        setValues({ ...values, current_password })
                    }
                    error={errors.fields.current_password}
                />
                <FormField
                    label="New password"
                    name="password"
                    type="password"
                    autoComplete="new-password"
                    required
                    value={values.password}
                    onChange={(password) => setValues({ ...values, password })}
                    error={errors.fields.password}
                />
                <FormField
                    label="New password again"
                    name="password_confirmation"
                    type="password"
                    autoComplete="new-password"
                    required
                    value={values.password_confirmation}
                    onChange={(password_confirmation) =>
                        setValues({ ...values, password_confirmation })
                    }
                    error={errors.fields.password_confirmation}
                />
                <button
                    type="submit"
                    className="btn btn-primary"
                    disabled={saving}
                >
                    {saving ? "Changing…" : "Change password"}
                </button>
                <span className="ms-3 small text-success" role="status">
                    {status}
                </span>
            </form>
        </section>
    );
}

export function DeleteAccount() {
    const queryClient = useQueryClient();
    const navigate = useNavigate();
    const id = useId();
    const [open, setOpen] = useState(false);
    const [password, setPassword] = useState("");
    const [errors, setErrors] = useState({ fields: {} });
    const [deleting, setDeleting] = useState(false);

    async function submit(event) {
        event.preventDefault();
        setDeleting(true);
        setErrors({ fields: {} });
        try {
            await ensureCsrfCookie();
            await api.delete("/me", { data: { password } });
            queryClient.setQueryData(["me"], null);
            navigate("/", { replace: true });
        } catch (error) {
            setErrors(toFormErrors(error));
            setDeleting(false);
        }
    }

    return (
        <section
            className="pa-account-section"
            aria-labelledby={`${id}-heading`}
        >
            <h2 id={`${id}-heading`} className="h5">
                Delete my account
            </h2>
            <p className="small text-body-secondary">
                Your account and testimonials that are not published yet are
                removed for good. Published testimonials stay on the website
                without a link to your account; ask us if you want one removed.
            </p>
            {!open ? (
                <button
                    type="button"
                    className="btn btn-outline-danger"
                    onClick={() => setOpen(true)}
                    aria-expanded={false}
                    aria-controls={`${id}-form`}
                >
                    Delete my account…
                </button>
            ) : (
                <form id={`${id}-form`} onSubmit={submit} noValidate>
                    <FormField
                        label="Your password, to confirm"
                        name="password"
                        type="password"
                        autoComplete="current-password"
                        required
                        value={password}
                        onChange={setPassword}
                        error={errors.fields.password}
                    />
                    {errors.message && !errors.fields.password && (
                        <div className="alert alert-danger" role="alert">
                            {errors.message}
                        </div>
                    )}
                    <div className="d-flex gap-2">
                        <button
                            type="submit"
                            className="btn btn-danger"
                            disabled={deleting || password === ""}
                        >
                            {deleting
                                ? "Deleting…"
                                : "Delete my account for good"}
                        </button>
                        <button
                            type="button"
                            className="btn btn-outline-secondary"
                            onClick={() => setOpen(false)}
                        >
                            Cancel
                        </button>
                    </div>
                </form>
            )}
        </section>
    );
}
