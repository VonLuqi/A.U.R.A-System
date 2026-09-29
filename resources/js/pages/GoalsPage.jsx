import { useCallback, useMemo, useState } from 'react';
import { Plus } from 'lucide-react';
import DeleteGoalDialog from '../components/goals/DeleteGoalDialog';
import GoalFormModal from '../components/goals/GoalFormModal';
import GoalsPanel from '../components/goals/GoalsPanel';
import Button from '../components/ui/Button';
import PageHeader from '../components/layout/PageHeader';
import { useCategories } from '../hooks/useCategories';
import { useDocumentTitle } from '../hooks/useDocumentTitle';
import {
    useCreateGoal,
    useDeleteGoal,
    useUpdateGoal,
} from '../hooks/useGoalMutations';
import { useGoals } from '../hooks/useGoals';

/**
 * GoalsPage — CRUD de metas (PLAN_EXPANSAO §8.5).
 */
export default function GoalsPage() {
    useDocumentTitle('Metas · Aura');

    const filters = useMemo(() => ({ per_page: 50 }), []);
    const goals = useGoals(filters);
    const categories = useCategories();

    const [formState, setFormState] = useState(null);
    const [deleteTarget, setDeleteTarget] = useState(null);

    const refresh = useCallback(async () => {
        await goals.refetch();
    }, [goals.refetch]);

    const createGoal = useCreateGoal({ onSuccess: refresh });
    const updateGoal = useUpdateGoal({ onSuccess: refresh });
    const deleteGoal = useDeleteGoal({ onSuccess: refresh });

    const formOpen = formState !== null;
    const formMode = formState?.mode === 'edit' ? 'edit' : 'create';
    const formSubmitting =
        formMode === 'edit' ? updateGoal.isLoading : createGoal.isLoading;

    return (
        <div className="flex flex-col gap-8 md:gap-10">
            <PageHeader
                title="Metas"
                description="Acompanhe poupança e amortização de dívidas."
                actions={(
                    <Button
                        type="button"
                        size="sm"
                        onClick={() => setFormState({ mode: 'create' })}
                    >
                        <Plus size={16} strokeWidth={2} aria-hidden />
                        Nova meta
                    </Button>
                )}
            />

            <GoalsPanel
                goals={goals.data}
                meta={goals.meta}
                status={goals.status}
                error={goals.error}
                onRetry={goals.refetch}
                onCreate={() => setFormState({ mode: 'create' })}
                onEdit={(goal) => setFormState({ mode: 'edit', goal })}
                onDelete={(goal) => setDeleteTarget(goal)}
            />

            <GoalFormModal
                open={formOpen}
                mode={formMode}
                goal={formState?.goal ?? null}
                categories={categories.data}
                categoriesLoading={categories.status === 'loading'}
                submitting={formSubmitting}
                onClose={() => {
                    if (!formSubmitting) {
                        setFormState(null);
                    }
                }}
                onSubmit={async (payload) => {
                    if (formMode === 'edit' && formState?.goal?.id != null) {
                        await updateGoal.mutate(formState.goal.id, payload);
                    } else {
                        await createGoal.mutate(payload);
                    }
                    setFormState(null);
                }}
            />

            <DeleteGoalDialog
                open={deleteTarget !== null}
                goal={deleteTarget}
                submitting={deleteGoal.isLoading}
                onClose={() => {
                    if (!deleteGoal.isLoading) {
                        setDeleteTarget(null);
                    }
                }}
                onConfirm={async () => {
                    if (!deleteTarget?.id) {
                        return;
                    }
                    await deleteGoal.mutate(deleteTarget.id);
                    setDeleteTarget(null);
                }}
            />
        </div>
    );
}
