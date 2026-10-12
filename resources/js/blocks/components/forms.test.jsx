import { beforeEach, describe, expect, it, vi } from "vitest";
import { fireEvent, render, screen } from "@testing-library/react";
import { MemoryRouter } from "react-router";
import BlockRenderer from "../BlockRenderer.jsx";
import { highlight } from "../../public/utils/highlight.jsx";

// A plain function, not a spy: a spy reports a rejected promise as a test failure even when the component catches it.
const calls = [];
let reply = () => Promise.resolve({ data: {} });
vi.mock("../../public/api/client.js", () => ({
    api: {
        post: (...args) => {
            calls.push(args);
            return reply();
        },
    },
    ensureCsrfCookie: () => Promise.resolve(),
    toFormErrors: (error) => ({
        message: error.response.data.message,
        fields: Object.fromEntries(
            Object.entries(error.response.data.errors ?? {}).map(
                ([key, value]) => [key, value[0]],
            ),
        ),
    }),
}));

const contact = (content = {}) => ({
    uuid: "01k0000000000000000contact",
    type: "contact-form",
    content: {
        heading: "Write to us",
        show_subject: true,
        consent_text: "I agree.",
        button_label: "Send",
        ...content,
    },
    data: { has_consent: true },
});

function show(nodes, path = "/contact") {
    return render(
        <MemoryRouter initialEntries={[path]}>
            <BlockRenderer nodes={nodes} />
        </MemoryRouter>,
    );
}

function fill() {
    fireEvent.change(screen.getByLabelText(/Your name/), {
        target: { value: "Nadia" },
    });
    fireEvent.change(screen.getByLabelText(/Your e-mail/), {
        target: { value: "nadia@example.org" },
    });
    fireEvent.change(screen.getByLabelText(/^Message/), {
        target: { value: "I would like to volunteer." },
    });
    fireEvent.click(screen.getByLabelText("I agree."));
}

describe("ContactFormBlock", () => {
    beforeEach(() => {
        calls.length = 0;
    });

    it("sends the block id, page and an empty spam trap, then shows the thank-you text", async () => {
        reply = () =>
            Promise.resolve({
                data: { message: "Thanks, we will reply soon." },
            });
        show([contact()]);
        fill();
        fireEvent.click(screen.getByRole("button", { name: "Send" }));

        expect(await screen.findByRole("status")).toHaveTextContent(
            "Thanks, we will reply soon.",
        );
        const [url, body] = calls[0];
        expect(url).toBe("/contact");
        expect(body).toMatchObject({
            block: "01k0000000000000000contact",
            page: "/contact",
            name: "Nadia",
            consent: true,
            website: "",
        });
        expect(body.elapsed).toBeTypeOf("number");
        expect(body).not.toHaveProperty("recipient");
    });

    it("shows field errors from the server next to the fields", async () => {
        reply = () =>
            Promise.reject(
                Object.assign(new Error("Invalid."), {
                    response: {
                        data: {
                            message: "Invalid.",
                            errors: {
                                email: ["Enter a valid e-mail address."],
                            },
                        },
                    },
                }),
            );
        show([contact()]);
        fill();
        fireEvent.click(screen.getByRole("button", { name: "Send" }));

        expect(
            await screen.findByText("Enter a valid e-mail address."),
        ).toBeInTheDocument();
        expect(screen.getByLabelText(/Your e-mail/)).toHaveAttribute(
            "aria-invalid",
            "true",
        );
    });

    it("hides the subject and consent when the block turns them off", () => {
        show([contact({ show_subject: false, consent_text: "" })]);
        expect(screen.queryByLabelText(/Subject/)).toBeNull();
        expect(screen.queryByRole("checkbox")).toBeNull();
    });
});

describe("SearchBlock", () => {
    const search = (style = "icon") => ({
        uuid: "01k00000000000000000search",
        type: "search",
        content: { style, placeholder: "Search…" },
    });

    it("opens the search box from the icon and Esc closes it again", () => {
        show([search()], "/");
        const toggle = screen.getByRole("button", { name: "Search" });
        expect(toggle).toHaveAttribute("aria-expanded", "false");

        fireEvent.click(toggle);
        expect(toggle).toHaveAttribute("aria-expanded", "true");
        expect(screen.getByRole("search")).toBeInTheDocument();

        fireEvent.keyDown(screen.getByLabelText("Search this site"), {
            key: "Escape",
        });
        expect(toggle).toHaveAttribute("aria-expanded", "false");
        expect(toggle).toHaveFocus();
    });

    it("shows the box straight away in the box style", () => {
        show([search("box")], "/");
        expect(screen.getByRole("search")).toBeInTheDocument();
        expect(screen.queryByRole("button", { expanded: false })).toBeNull();
    });
});

describe("highlight", () => {
    it("marks each word of the query without injecting HTML", () => {
        const { container } = render(
            <p>
                {highlight(
                    "Clean water for <b>schools</b> and water points",
                    "water <b>",
                )}
            </p>,
        );
        expect(
            [...container.querySelectorAll("mark")].map(
                (mark) => mark.textContent,
            ),
        ).toEqual(["water", "water"]);
        expect(container.querySelector("b")).toBeNull();
    });

    it("returns the text unchanged for very short queries", () => {
        expect(highlight("Water", "a")).toBe("Water");
    });
});
