import BrandMark from '../components/ui/BrandMark';
import Card from '../components/ui/Card';
import LoginForm from '../components/auth/LoginForm';
import { useDocumentTitle } from '../hooks/useDocumentTitle';

/**
 * LoginPage — tokens obrigatórios (Etapa D §1.3.3 / §5.5).
 */
export default function LoginPage() {
    useDocumentTitle('Login · Aura');

    return (
        <main className="relative flex min-h-dvh flex-col items-center justify-center overflow-hidden bg-canvas px-6 py-10 font-sans">
            <div
                aria-hidden
                className="pointer-events-none absolute inset-0 bg-[radial-gradient(ellipse_at_50%_0%,var(--color-brand-glow),transparent_55%)]"
            />

            <div className="relative z-10 flex w-full max-w-md flex-col items-center gap-8">
                <header className="flex flex-col items-center gap-3 text-center">
                    <BrandMark size="lg" />
                    <p className="max-w-sm text-caption font-normal text-ink-secondary">
                        Inteligência invisível, controle absoluto.
                    </p>
                </header>

                <Card className="w-full shadow-none">
                    <LoginForm />
                </Card>
            </div>
        </main>
    );
}
